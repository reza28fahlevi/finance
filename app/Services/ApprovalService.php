<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private TransactionService $transactions,
        private JournalService $journals,
        private PostingService $posting,
        private AuditLogger $audit,
        private Settings $settings,
    ) {}

    /**
     * Cek apakah user boleh memutuskan transaksi ini. Mengembalikan alasan penolakan, atau null bila boleh.
     */
    public function denialReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasPermission('approval.act')) {
            return 'Peran Anda tidak memiliki hak approval.';
        }
        // Segregation of duties: maker ≠ checker.
        if ($transaction->created_by === $user->id) {
            return 'Pembuat transaksi tidak boleh menyetujui/menolak transaksinya sendiri (Maker ≠ Checker).';
        }
        if ($this->requiresManager($transaction) && $user->role !== Role::Manager) {
            return 'Nominal ≥ '.Money::format($this->settings->get('approval_manager_threshold')).' memerlukan approval Manager.';
        }

        return null;
    }

    public function requiresManager(Transaction $transaction): bool
    {
        return $this->settings->get('approval_tiered')
            && Money::cmp((string) $transaction->amount, $this->settings->get('approval_manager_threshold')) >= 0;
    }

    /**
     * PENDING_REVIEW → APPROVED, lalu terbitkan jurnal otomatis (→ JOURNALED).
     * Bila auto_post_on_approve aktif, jurnal langsung diposting ke GL.
     *
     * @return array{transaction: Transaction, journal: JournalEntry}
     */
    public function approve(Transaction $transaction, User $user, ?string $note = null): array
    {
        return DB::transaction(function () use ($transaction, $user, $note) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->guard($transaction, $user);
            $this->transactions->assertTransition($transaction, TransactionStatus::Approved);

            $transaction->approvals()->create([
                'approver_id' => $user->id,
                'action' => ApprovalAction::Approve,
                'note' => $note,
                'acted_at' => now(),
            ]);
            $transaction->update(['status' => TransactionStatus::Approved]);
            $this->audit->statusChange($transaction, 'transaction.approved', TransactionStatus::PendingReview->value, TransactionStatus::Approved->value, $note);

            $journal = $this->journals->generateFromTransaction($transaction, $user);

            if ($this->settings->get('auto_post_on_approve')) {
                $journal = $this->posting->post($journal, $user);
            }

            return ['transaction' => $transaction->fresh(), 'journal' => $journal];
        });
    }

    /** PENDING_REVIEW → REJECTED (alasan wajib). */
    public function reject(Transaction $transaction, User $user, string $reason): Transaction
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($transaction, $user, $reason) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->guard($transaction, $user);
            $this->transactions->assertTransition($transaction, TransactionStatus::Rejected);

            $transaction->approvals()->create([
                'approver_id' => $user->id,
                'action' => ApprovalAction::Reject,
                'note' => $reason,
                'acted_at' => now(),
            ]);
            $transaction->update(['status' => TransactionStatus::Rejected, 'rejection_reason' => $reason]);
            $this->audit->statusChange($transaction, 'transaction.rejected', TransactionStatus::PendingReview->value, TransactionStatus::Rejected->value, $reason);

            return $transaction;
        });
    }

    private function guard(Transaction $transaction, User $user): void
    {
        if ($reason = $this->denialReason($transaction, $user)) {
            throw new BusinessRuleException($reason);
        }
    }
}
