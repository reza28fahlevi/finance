<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        private PeriodService $periods,
        private SequenceService $sequences,
        private AttachmentService $attachments,
        private AuditLogger $audit,
        private Settings $settings,
    ) {}

    /**
     * Buat transaksi berstatus DRAFT beserta lampiran (opsional).
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public function create(TransactionType $type, array $data, User $user, array $files = []): Transaction
    {
        $date = Carbon::parse($data['trx_date']);
        $period = $this->periods->assertOpenForDate($date);
        $amount = $this->validateAmountAndAccounts($data);

        return DB::transaction(function () use ($type, $data, $user, $files, $date, $period, $amount) {
            $transaction = Transaction::create([
                'number' => $this->sequences->next(
                    $this->settings->get('transaction_ref_format'),
                    $date,
                    ['TYPE' => $type->code()],
                ),
                'type' => $type,
                'trx_date' => $date->toDateString(),
                'due_date' => $type->isInvoice() ? ($data['due_date'] ?? null) : null,
                'description' => $data['description'],
                'amount' => $amount,
                'debit_account_id' => $data['debit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'party_name' => $data['party_name'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => TransactionStatus::Draft,
                'period_id' => $period->id,
                'created_by' => $user->id,
            ]);

            $this->audit->statusChange($transaction, 'transaction.created', null, TransactionStatus::Draft->value);

            foreach ($files as $file) {
                $this->attachments->store($transaction, $file, $user);
            }

            return $transaction;
        });
    }

    /**
     * Ubah transaksi Draft/Rejected. Transaksi Rejected yang direvisi kembali ke DRAFT.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public function update(Transaction $transaction, array $data, User $user, array $files = []): Transaction
    {
        if (! $transaction->isEditable()) {
            throw new BusinessRuleException("Transaksi {$transaction->number} berstatus {$transaction->status->label()} dan terkunci dari edit.");
        }

        $date = Carbon::parse($data['trx_date']);
        $period = $this->periods->assertOpenForDate($date);
        $amount = $this->validateAmountAndAccounts($data);

        return DB::transaction(function () use ($transaction, $data, $user, $files, $date, $period, $amount) {
            $before = $transaction->only(['trx_date', 'description', 'amount', 'debit_account_id', 'credit_account_id', 'party_name']);
            $before['trx_date'] = $transaction->trx_date->toDateString();
            $wasRejected = $transaction->status === TransactionStatus::Rejected;

            $transaction->update([
                'trx_date' => $date->toDateString(),
                'due_date' => $transaction->type->isInvoice() ? ($data['due_date'] ?? null) : null,
                'description' => $data['description'],
                'amount' => $amount,
                'debit_account_id' => $data['debit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'party_name' => $data['party_name'] ?? null,
                'reference' => $data['reference'] ?? null,
                'period_id' => $period->id,
                'status' => TransactionStatus::Draft,
            ]);

            $this->audit->log('transaction.updated', $transaction, $before, $transaction->only(array_keys($before)));
            if ($wasRejected) {
                $this->audit->statusChange($transaction, 'transaction.revised', TransactionStatus::Rejected->value, TransactionStatus::Draft->value);
            }

            foreach ($files as $file) {
                $this->attachments->store($transaction, $file, $user);
            }

            return $transaction;
        });
    }

    /** DRAFT → PENDING_REVIEW. Transaksi terkunci dari edit setelah submit. */
    public function submit(Transaction $transaction, User $user): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->assertTransition($transaction, TransactionStatus::PendingReview);
            $this->periods->assertOpenForDate($transaction->trx_date);
            $this->validateAmountAndAccounts($transaction->only(['amount', 'debit_account_id', 'credit_account_id']));

            $threshold = $this->settings->get('attachment_required_above');
            if (Money::cmp((string) $transaction->amount, $threshold) === 1 && $transaction->attachments()->doesntExist()) {
                throw new BusinessRuleException('Transaksi dengan nominal di atas '.Money::format($threshold).' wajib memiliki minimal 1 lampiran.');
            }

            $transaction->update([
                'status' => TransactionStatus::PendingReview,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->audit->statusChange($transaction, 'transaction.submitted', TransactionStatus::Draft->value, TransactionStatus::PendingReview->value);

            return $transaction;
        });
    }

    /** DRAFT → CANCELLED. */
    public function cancel(Transaction $transaction, ?string $reason = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->assertTransition($transaction, TransactionStatus::Cancelled);

            $transaction->update(['status' => TransactionStatus::Cancelled]);
            $this->audit->statusChange($transaction, 'transaction.cancelled', TransactionStatus::Draft->value, TransactionStatus::Cancelled->value, $reason);

            return $transaction;
        });
    }

    /** @throws BusinessRuleException */
    public function assertTransition(Transaction $transaction, TransactionStatus $to): void
    {
        if (! $transaction->status->canTransitionTo($to)) {
            throw new BusinessRuleException("Transisi status {$transaction->status->label()} → {$to->label()} tidak diizinkan untuk {$transaction->number}.");
        }
    }

    /**
     * Nominal > 0; akun debit & kredit wajib, berbeda, aktif & postable.
     *
     * @param  array<string, mixed>  $data
     */
    private function validateAmountAndAccounts(array $data): string
    {
        $amount = Money::normalize($data['amount'] ?? null);
        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException('Nominal harus lebih besar dari 0.');
        }

        if (empty($data['debit_account_id']) || empty($data['credit_account_id'])) {
            throw new BusinessRuleException('Akun debit dan kredit wajib diisi.');
        }
        if ((int) $data['debit_account_id'] === (int) $data['credit_account_id']) {
            throw new BusinessRuleException('Akun debit dan kredit tidak boleh sama.');
        }

        $accounts = Account::whereIn('id', [$data['debit_account_id'], $data['credit_account_id']])->get();
        if ($accounts->count() !== 2 || $accounts->contains(fn (Account $a) => ! $a->isUsable())) {
            throw new BusinessRuleException('Akun yang dipilih harus aktif dan merupakan akun postable.');
        }

        return $amount;
    }
}
