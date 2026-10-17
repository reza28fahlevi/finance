<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Supervisor = 'supervisor';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staff Finance (Maker)',
            self::Supervisor => 'Supervisor (Checker)',
            self::Manager => 'Manager Finance (Checker)',
            self::Accountant => 'Akuntan',
            self::Auditor => 'Auditor',
        };
    }

    public function isChecker(): bool
    {
        return in_array($this, [self::Supervisor, self::Manager], true);
    }

    /**
     * Hak akses per peran (RANCANGAN.md 10.4 Matriks Akses Menu).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => [
                'master.view', 'master.manage', 'period.close', 'period.reopen',
                'transaction.view', 'journal.view', 'gl.view',
                'users.manage', 'audit.view', 'settings.manage',
            ],
            self::Staff => [
                'master.view', 'transaction.view', 'transaction.manage',
            ],
            self::Supervisor, self::Manager => [
                'master.view', 'transaction.view', 'approval.view', 'approval.act',
                'journal.view', 'gl.view',
            ],
            self::Accountant => [
                'master.view', 'period.close', 'transaction.view',
                'journal.view', 'journal.manage', 'gl.view',
            ],
            self::Auditor => [
                'master.view', 'transaction.view', 'approval.view',
                'journal.view', 'gl.view', 'audit.view',
            ],
        };
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<string> */
    public static function allPermissions(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (self $role) => $role->permissions(),
            self::cases(),
        ))));
    }
}
