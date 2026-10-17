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
     * Hak akses DEFAULT per peran (RANCANGAN.md 10.4 Matriks Akses Menu).
     * Hak akses aktif disimpan di tabel role_permissions dan dapat diubah lewat
     * menu Pengaturan → Role & Permission (lihat App\Support\Permissions).
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => [
                'master.view', 'master.manage', 'period.close', 'period.reopen',
                'transaction.view', 'journal.view', 'gl.view',
                'users.manage', 'roles.manage', 'audit.view', 'settings.manage',
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

    public function defaultAllows(string $permission): bool
    {
        return in_array($permission, $this->defaultPermissions(), true);
    }
}
