<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transaction.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->hasPermission('transaction.view') || $user->hasPermission('approval.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transaction.manage');
    }

    /** Maker hanya dapat mengubah transaksi miliknya yang berstatus Draft/Rejected. */
    public function update(User $user, Transaction $transaction): Response
    {
        if (! $user->hasPermission('transaction.manage')) {
            return Response::deny('Peran Anda tidak dapat mengubah transaksi.');
        }
        if ($transaction->created_by !== $user->id) {
            return Response::deny('Hanya pembuat transaksi yang dapat mengubahnya.');
        }
        if (! $transaction->isEditable()) {
            return Response::deny("Transaksi berstatus {$transaction->status->label()} terkunci dari edit.");
        }

        return Response::allow();
    }

    public function submit(User $user, Transaction $transaction): Response
    {
        return $this->ownDraftAction($user, $transaction);
    }

    public function cancel(User $user, Transaction $transaction): Response
    {
        return $this->ownDraftAction($user, $transaction);
    }

    public function decide(User $user, Transaction $transaction): Response
    {
        if ($transaction->status !== TransactionStatus::PendingReview) {
            return Response::deny('Transaksi tidak sedang menunggu review.');
        }

        $reason = app(ApprovalService::class)->denialReason($transaction, $user);

        return $reason ? Response::deny($reason) : Response::allow();
    }

    private function ownDraftAction(User $user, Transaction $transaction): Response
    {
        if (! $user->hasPermission('transaction.manage') || $transaction->created_by !== $user->id) {
            return Response::deny('Hanya pembuat transaksi yang dapat melakukan aksi ini.');
        }
        if ($transaction->status !== TransactionStatus::Draft) {
            return Response::deny('Aksi hanya untuk transaksi berstatus Draft.');
        }

        return Response::allow();
    }
}
