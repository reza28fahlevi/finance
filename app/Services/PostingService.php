<?php

namespace App\Services;

use App\Enums\JournalStatus;
use App\Enums\NormalBalance;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AccountingPeriod;
use App\Models\GlEntry;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Posting jurnal ke General Ledger (RANCANGAN.md Tahap 5).
 * Seluruh proses berjalan dalam satu DB transaction; gagal di tengah = rollback penuh.
 */
class PostingService
{
    public function __construct(
        private PeriodService $periods,
        private JournalService $journals,
        private AuditLogger $audit,
    ) {}

    /** @throws BusinessRuleException */
    public function post(JournalEntry $journal, User $user): JournalEntry
    {
        return DB::transaction(function () use ($journal, $user) {
            // 1. Kunci baris jurnal; pastikan belum diposting (idempotent, cegah posting ganda).
            $journal = JournalEntry::with('lines')->lockForUpdate()->findOrFail($journal->id);

            if ($journal->status !== JournalStatus::Draft) {
                throw new BusinessRuleException("Jurnal {$journal->ref_no} sudah berstatus {$journal->status->value}; tidak dapat diposting ulang.");
            }

            $period = AccountingPeriod::lockForUpdate()->findOrFail($journal->period_id);
            if (! $period->isOpen()) {
                throw new BusinessRuleException("Periode {$period->code} sudah CLOSED; posting ditolak.");
            }

            // Validasi ulang saat posting: seimbang, akun aktif & postable.
            $this->journals->validateLines($journal->lines->map(fn (JournalLine $l) => $l->only(['account_id', 'description', 'debit', 'credit']))->all());

            $accounts = Account::whereIn('id', $journal->lines->pluck('account_id'))->get()->keyBy('id');

            // Proses berurutan per akun agar urutan penguncian konsisten (menghindari deadlock).
            $lines = $journal->lines->sortBy([['account_id', 'asc'], ['line_no', 'asc']]);

            foreach ($lines as $line) {
                $account = $accounts[$line->account_id];
                $debit = Money::normalize($line->debit);
                $credit = Money::normalize($line->credit);

                // 2. Tambah mutasi ke account_balances per (akun, periode).
                $balance = $this->lockBalance($account, $period);
                $delta = $this->balanceDelta($account->normal_balance, $debit, $credit);

                $balance->total_debit = Money::add((string) $balance->total_debit, $debit);
                $balance->total_credit = Money::add((string) $balance->total_credit, $credit);
                // 3. Saldo akhir = awal + debit − kredit (normal debit) / awal + kredit − debit (normal kredit).
                $balance->closing_balance = Money::add((string) $balance->closing_balance, $delta);
                $balance->save();

                // 4. Tulis gl_entries dengan saldo berjalan.
                GlEntry::create([
                    'account_id' => $account->id,
                    'period_id' => $period->id,
                    'journal_line_id' => $line->id,
                    'journal_id' => $journal->id,
                    'entry_date' => $journal->entry_date->toDateString(),
                    'description' => $line->description ?? $journal->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => $balance->closing_balance,
                ]);

                // Bila sudah ada saldo di periode setelahnya, geser saldo awal/akhir & saldo berjalannya.
                $this->propagateToLaterPeriods($account, $period, $delta);
            }

            // 5. Set jurnal POSTED.
            $journal->update([
                'status' => JournalStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $user->id,
            ]);
            $this->audit->statusChange($journal, 'journal.posted', JournalStatus::Draft->value, JournalStatus::Posted->value);

            return $journal;
        });
    }

    /**
     * Posting beberapa jurnal sekaligus; tiap jurnal di transaksi DB sendiri.
     *
     * @param  iterable<int>  $journalIds
     * @return list<array{id:int, ref_no:string, success:bool, message:string}>
     */
    public function postBatch(iterable $journalIds, User $user): array
    {
        $results = [];
        foreach ($journalIds as $id) {
            $journal = JournalEntry::find($id);
            if (! $journal) {
                $results[] = ['id' => (int) $id, 'ref_no' => '#'.$id, 'success' => false, 'message' => 'Jurnal tidak ditemukan.'];

                continue;
            }

            try {
                $this->post($journal, $user);
                $results[] = ['id' => $journal->id, 'ref_no' => $journal->ref_no, 'success' => true, 'message' => 'Berhasil diposting.'];
            } catch (BusinessRuleException $e) {
                $results[] = ['id' => $journal->id, 'ref_no' => $journal->ref_no, 'success' => false, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['id' => $journal->id, 'ref_no' => $journal->ref_no, 'success' => false, 'message' => 'Kesalahan sistem: '.$e->getMessage()];
            }
        }

        return $results;
    }

    public function balanceDelta(NormalBalance $normal, string $debit, string $credit): string
    {
        return $normal === NormalBalance::Debit ? Money::sub($debit, $credit) : Money::sub($credit, $debit);
    }

    /** Saldo akhir akun pada periode terakhir sebelum $period (carry-forward), 0 bila belum ada. */
    public function previousClosing(Account $account, AccountingPeriod $period): string
    {
        $value = AccountBalance::query()
            ->join('accounting_periods as p', 'p.id', '=', 'account_balances.period_id')
            ->where('account_balances.account_id', $account->id)
            ->where('p.start_date', '<', $period->start_date->toDateString())
            ->orderByDesc('p.start_date')
            ->value('account_balances.closing_balance');

        return Money::normalize($value ?? '0');
    }

    private function lockBalance(Account $account, AccountingPeriod $period): AccountBalance
    {
        $opening = $this->previousClosing($account, $period);

        DB::table('account_balances')->insertOrIgnore([
            'account_id' => $account->id,
            'period_id' => $period->id,
            'opening_balance' => $opening,
            'total_debit' => 0,
            'total_credit' => 0,
            'closing_balance' => $opening,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AccountBalance::where('account_id', $account->id)
            ->where('period_id', $period->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function propagateToLaterPeriods(Account $account, AccountingPeriod $period, string $delta): void
    {
        if (Money::isZero($delta)) {
            return;
        }

        $laterPeriodIds = AccountingPeriod::where('start_date', '>', $period->start_date->toDateString())->pluck('id');
        if ($laterPeriodIds->isEmpty()) {
            return;
        }

        AccountBalance::where('account_id', $account->id)
            ->whereIn('period_id', $laterPeriodIds)
            ->lockForUpdate()
            ->get()
            ->each(function (AccountBalance $b) use ($delta) {
                $b->opening_balance = Money::add((string) $b->opening_balance, $delta);
                $b->closing_balance = Money::add((string) $b->closing_balance, $delta);
                $b->save();
            });

        GlEntry::where('account_id', $account->id)
            ->whereIn('period_id', $laterPeriodIds)
            ->update(['running_balance' => DB::raw('running_balance + '.Money::normalize($delta))]);
    }
}
