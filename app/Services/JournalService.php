<?php

namespace App\Services;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JournalService
{
    public function __construct(
        private PeriodService $periods,
        private SequenceService $sequences,
        private AuditLogger $audit,
        private Settings $settings,
    ) {}

    /**
     * Validasi & normalisasi baris jurnal (RANCANGAN.md Tahap 4):
     * min. 2 baris, satu baris hanya debit ATAU kredit, akun aktif & postable, SUM(debit) = SUM(kredit).
     *
     * @param  array<int, array{account_id:mixed, description?:mixed, debit?:mixed, credit?:mixed}>  $lines
     * @return list<array{line_no:int, account_id:int, description:?string, debit:string, credit:string}>
     *
     * @throws BusinessRuleException
     */
    public function validateLines(array $lines): array
    {
        $normalized = [];
        foreach (array_values($lines) as $i => $line) {
            $debit = Money::normalize($line['debit'] ?? null);
            $credit = Money::normalize($line['credit'] ?? null);
            $no = $i + 1;

            if (Money::isZero($debit) && Money::isZero($credit) && empty($line['account_id'])) {
                continue; // baris kosong diabaikan
            }
            if (Money::cmp($debit, '0') < 0 || Money::cmp($credit, '0') < 0) {
                throw new BusinessRuleException("Baris {$no}: nilai debit/kredit tidak boleh negatif.");
            }
            if (Money::isPositive($debit) === Money::isPositive($credit)) {
                throw new BusinessRuleException("Baris {$no}: isi salah satu saja, debit ATAU kredit.");
            }
            if (empty($line['account_id'])) {
                throw new BusinessRuleException("Baris {$no}: akun wajib dipilih.");
            }

            $normalized[] = [
                'line_no' => count($normalized) + 1,
                'account_id' => (int) $line['account_id'],
                'description' => isset($line['description']) && $line['description'] !== '' ? mb_substr((string) $line['description'], 0, 255) : null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($normalized) < 2) {
            throw new BusinessRuleException('Jurnal minimal terdiri dari 2 baris.');
        }

        $accounts = Account::whereIn('id', array_column($normalized, 'account_id'))->get()->keyBy('id');
        foreach ($normalized as $line) {
            $account = $accounts->get($line['account_id']);
            if (! $account) {
                throw new BusinessRuleException("Baris {$line['line_no']}: akun tidak ditemukan.");
            }
            if (! $account->isUsable()) {
                throw new BusinessRuleException("Baris {$line['line_no']}: akun {$account->code} tidak aktif atau bukan akun postable.");
            }
        }

        $totalDebit = array_reduce($normalized, fn ($s, $l) => Money::add($s, $l['debit']), '0.00');
        $totalCredit = array_reduce($normalized, fn ($s, $l) => Money::add($s, $l['credit']), '0.00');

        if (Money::cmp($totalDebit, $totalCredit) !== 0) {
            $diff = Money::format(Money::sub($totalDebit, $totalCredit));
            throw new BusinessRuleException('Jurnal tidak seimbang: total debit '.Money::format($totalDebit).' ≠ total kredit '.Money::format($totalCredit)." (selisih {$diff}).");
        }

        return $normalized;
    }

    /**
     * @param  array{entry_date:string, description:string, lines:array}  $data
     */
    public function createManual(array $data, User $user): JournalEntry
    {
        $date = Carbon::parse($data['entry_date']);
        $period = $this->periods->assertOpenForDate($date);
        $lines = $this->validateLines($data['lines']);

        return DB::transaction(function () use ($data, $user, $date, $period, $lines) {
            $journal = JournalEntry::create([
                'ref_no' => $this->nextRefNo($date),
                'entry_date' => $date->toDateString(),
                'period_id' => $period->id,
                'source' => JournalSource::Manual,
                'status' => JournalStatus::Draft,
                'description' => $data['description'],
                'created_by' => $user->id,
            ]);
            $journal->lines()->createMany($lines);

            $this->audit->log('journal.created', $journal, null, [
                'ref_no' => $journal->ref_no,
                'source' => JournalSource::Manual->value,
                'total' => $journal->totalDebit(),
            ]);

            return $journal;
        });
    }

    /** Ubah jurnal DRAFT (header + baris). */
    public function updateDraft(JournalEntry $journal, array $data): JournalEntry
    {
        if ($journal->status !== JournalStatus::Draft) {
            throw new BusinessRuleException('Hanya jurnal DRAFT yang dapat diubah. Jurnal POSTED dikoreksi dengan jurnal pembalik.');
        }
        if ($journal->source === JournalSource::Auto) {
            throw new BusinessRuleException('Jurnal otomatis dari transaksi tidak dapat diubah manual.');
        }

        $date = Carbon::parse($data['entry_date']);
        $period = $this->periods->assertOpenForDate($date);
        $lines = $this->validateLines($data['lines']);

        return DB::transaction(function () use ($journal, $data, $date, $period, $lines) {
            $old = ['entry_date' => $journal->entry_date->toDateString(), 'description' => $journal->description, 'total' => $journal->totalDebit()];

            $journal->update([
                'entry_date' => $date->toDateString(),
                'period_id' => $period->id,
                'description' => $data['description'],
            ]);
            $journal->lines()->delete();
            $journal->lines()->createMany($lines);
            $journal->unsetRelation('lines');

            $this->audit->log('journal.updated', $journal, $old, [
                'entry_date' => $date->toDateString(),
                'description' => $data['description'],
                'total' => $journal->totalDebit(),
            ]);

            return $journal;
        });
    }

    public function deleteDraft(JournalEntry $journal): void
    {
        if ($journal->status !== JournalStatus::Draft || $journal->source === JournalSource::Auto) {
            throw new BusinessRuleException('Hanya jurnal manual berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($journal) {
            $this->audit->log('journal.deleted', $journal, ['ref_no' => $journal->ref_no, 'total' => $journal->totalDebit()]);
            $journal->delete();
        });
    }

    /**
     * Terbitkan jurnal otomatis dari transaksi APPROVED memakai akun debit/kredit transaksi
     * (diisi default dari Template Jurnal Otomatis saat input). Transaksi menjadi JOURNALED.
     */
    public function generateFromTransaction(Transaction $transaction, User $user): JournalEntry
    {
        return DB::transaction(function () use ($transaction, $user) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== TransactionStatus::Approved) {
                throw new BusinessRuleException("Transaksi {$transaction->number} belum APPROVED.");
            }
            if (JournalEntry::where('transaction_id', $transaction->id)->exists()) {
                throw new BusinessRuleException("Transaksi {$transaction->number} sudah memiliki jurnal.");
            }

            $period = $this->periods->assertOpenForDate($transaction->trx_date);
            $description = "{$transaction->type->label()} {$transaction->number}: {$transaction->description}";
            $lines = $this->validateLines([
                ['account_id' => $transaction->debit_account_id, 'description' => mb_substr($transaction->description, 0, 255), 'debit' => $transaction->amount, 'credit' => 0],
                ['account_id' => $transaction->credit_account_id, 'description' => mb_substr($transaction->description, 0, 255), 'debit' => 0, 'credit' => $transaction->amount],
            ]);

            $journal = JournalEntry::create([
                'ref_no' => $this->nextRefNo($transaction->trx_date),
                'entry_date' => $transaction->trx_date->toDateString(),
                'period_id' => $period->id,
                'transaction_id' => $transaction->id,
                'source' => JournalSource::Auto,
                'status' => JournalStatus::Draft,
                'description' => mb_substr($description, 0, 1000),
                'created_by' => $user->id,
            ]);
            $journal->lines()->createMany($lines);

            $transaction->update(['status' => TransactionStatus::Journaled]);
            $this->audit->statusChange($transaction, 'transaction.journaled', TransactionStatus::Approved->value, TransactionStatus::Journaled->value, "Jurnal {$journal->ref_no}");
            $this->audit->log('journal.created', $journal, null, [
                'ref_no' => $journal->ref_no,
                'source' => JournalSource::Auto->value,
                'transaction' => $transaction->number,
                'total' => $journal->totalDebit(),
            ]);

            return $journal;
        });
    }

    /**
     * Buat jurnal pembalik (debit/kredit ditukar) dari jurnal POSTED, langsung diposting.
     * Jurnal asli berubah status menjadi REVERSED.
     */
    public function reverse(JournalEntry $original, User $user, string $date, string $reason, PostingService $posting): JournalEntry
    {
        return DB::transaction(function () use ($original, $user, $date, $reason, $posting) {
            $original = JournalEntry::lockForUpdate()->findOrFail($original->id);

            if ($original->status !== JournalStatus::Posted) {
                throw new BusinessRuleException("Hanya jurnal POSTED yang dapat dibalik. Status {$original->ref_no}: {$original->status->value}.");
            }
            if (JournalEntry::where('reversal_of_id', $original->id)->exists()) {
                throw new BusinessRuleException("Jurnal {$original->ref_no} sudah pernah dibalik.");
            }

            $entryDate = Carbon::parse($date);
            $period = $this->periods->assertOpenForDate($entryDate);

            $lines = $original->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'description' => 'Pembalik: '.($line->description ?? ''),
                'debit' => $line->credit,
                'credit' => $line->debit,
            ])->all();

            $reversal = JournalEntry::create([
                'ref_no' => $this->nextRefNo($entryDate),
                'entry_date' => $entryDate->toDateString(),
                'period_id' => $period->id,
                'source' => JournalSource::Manual,
                'status' => JournalStatus::Draft,
                'description' => mb_substr("Jurnal pembalik {$original->ref_no}: {$reason}", 0, 1000),
                'reversal_of_id' => $original->id,
                'created_by' => $user->id,
            ]);
            $reversal->lines()->createMany($this->validateLines($lines));
            $this->audit->log('journal.created', $reversal, null, ['ref_no' => $reversal->ref_no, 'reversal_of' => $original->ref_no], $reason);

            $posting->post($reversal, $user);

            $original->status = JournalStatus::Reversed;
            $original->save();
            $this->audit->statusChange($original, 'journal.reversed', JournalStatus::Posted->value, JournalStatus::Reversed->value, "Dibalik oleh {$reversal->ref_no}: {$reason}");

            return $reversal->fresh();
        });
    }

    public function nextRefNo(Carbon|\DateTimeInterface $date): string
    {
        return $this->sequences->next($this->settings->get('journal_ref_format'), Carbon::parse($date));
    }
}
