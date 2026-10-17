<?php

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Enums\Role;
use App\Enums\TransactionStatus as S;
use App\Enums\TransactionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumRulesTest extends TestCase
{
    /** Transisi sah sesuai diagram state RANCANGAN.md 4.1. */
    public static function transitions(): array
    {
        return [
            'draft→pending' => [S::Draft, S::PendingReview, true],
            'draft→cancelled' => [S::Draft, S::Cancelled, true],
            'pending→approved' => [S::PendingReview, S::Approved, true],
            'pending→rejected' => [S::PendingReview, S::Rejected, true],
            'rejected→draft (revisi)' => [S::Rejected, S::Draft, true],
            'approved→journaled' => [S::Approved, S::Journaled, true],
            'draft→approved (lompat)' => [S::Draft, S::Approved, false],
            'pending→draft' => [S::PendingReview, S::Draft, false],
            'pending→cancelled' => [S::PendingReview, S::Cancelled, false],
            'approved→rejected' => [S::Approved, S::Rejected, false],
            'journaled→apa pun' => [S::Journaled, S::Draft, false],
            'cancelled→draft' => [S::Cancelled, S::Draft, false],
            'rejected→approved' => [S::Rejected, S::Approved, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transaction_status_transitions(S $from, S $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_only_draft_and_rejected_are_editable(): void
    {
        $editable = array_filter(S::cases(), fn (S $s) => $s->isEditable());
        $this->assertEqualsCanonicalizing([S::Draft, S::Rejected], array_values($editable));
    }

    /** Matriks akses menu RANCANGAN.md 10.4. */
    public static function permissionMatrix(): array
    {
        return [
            'admin kelola master' => [Role::Admin, 'master.manage', true],
            'admin kelola user' => [Role::Admin, 'users.manage', true],
            'admin tidak input transaksi' => [Role::Admin, 'transaction.manage', false],
            'admin tidak approve' => [Role::Admin, 'approval.act', false],
            'admin tidak kelola jurnal' => [Role::Admin, 'journal.manage', false],
            'staff input transaksi' => [Role::Staff, 'transaction.manage', true],
            'staff tidak lihat jurnal' => [Role::Staff, 'journal.view', false],
            'staff tidak lihat GL' => [Role::Staff, 'gl.view', false],
            'staff tidak approve' => [Role::Staff, 'approval.act', false],
            'supervisor approve' => [Role::Supervisor, 'approval.act', true],
            'manager approve' => [Role::Manager, 'approval.act', true],
            'manager tidak kelola jurnal' => [Role::Manager, 'journal.manage', false],
            'akuntan kelola jurnal' => [Role::Accountant, 'journal.manage', true],
            'akuntan tutup periode' => [Role::Accountant, 'period.close', true],
            'akuntan tidak approve' => [Role::Accountant, 'approval.act', false],
            'auditor lihat audit log' => [Role::Auditor, 'audit.view', true],
            'auditor lihat approval' => [Role::Auditor, 'approval.view', true],
            'auditor read-only transaksi' => [Role::Auditor, 'transaction.manage', false],
            'auditor read-only jurnal' => [Role::Auditor, 'journal.manage', false],
            'auditor tidak kelola pengaturan' => [Role::Auditor, 'settings.manage', false],
        ];
    }

    #[DataProvider('permissionMatrix')]
    public function test_role_permission_matrix(Role $role, string $permission, bool $expected): void
    {
        $this->assertSame($expected, $role->defaultAllows($permission));
    }

    public function test_default_normal_balance_per_account_type(): void
    {
        $this->assertSame(NormalBalance::Debit, AccountType::Asset->defaultNormalBalance());
        $this->assertSame(NormalBalance::Debit, AccountType::Expense->defaultNormalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Liability->defaultNormalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Equity->defaultNormalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Revenue->defaultNormalBalance());
    }

    public function test_transaction_type_slug_roundtrip(): void
    {
        foreach (TransactionType::cases() as $type) {
            $this->assertSame($type, TransactionType::fromSlug($type->slug()));
        }
        $this->expectException(\ValueError::class);
        TransactionType::fromSlug('tidak-ada');
    }
}
