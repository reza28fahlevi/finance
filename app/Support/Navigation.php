<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\User;

/**
 * Struktur menu aplikasi (RANCANGAN.md 10.1), difilter sesuai hak akses peran (10.4).
 */
class Navigation
{
    /** @return list<array{label:string, icon:string, route?:string, active:string, children?:list<array>, badge?:string}> */
    public static function for(User $user): array
    {
        $menus = [
            [
                'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard',
                'active' => 'dashboard', 'can' => null,
            ],
            [
                'label' => 'Master Data', 'icon' => 'dataset', 'active' => 'master.*', 'can' => 'master.view',
                'children' => [
                    ['label' => 'Chart of Accounts', 'route' => 'master.accounts.index', 'active' => 'master.accounts.*'],
                    ['label' => 'Periode Akuntansi', 'route' => 'master.periods.index', 'active' => 'master.periods.*'],
                    ['label' => 'Template Jurnal Otomatis', 'route' => 'master.templates.index', 'active' => 'master.templates.*'],
                ],
            ],
            [
                'label' => 'Transaksi', 'icon' => 'receipt_long', 'active' => 'transactions.*', 'can' => 'transaction.view',
                'children' => array_merge(
                    $user->hasPermission('transaction.manage')
                        ? array_map(fn (TransactionType $t) => [
                            'label' => $t->label(),
                            'route' => 'transactions.create',
                            'params' => ['type' => $t->slug()],
                            'active' => 'transactions.create',
                            'active_param' => ['type', $t->slug()],
                        ], TransactionType::cases())
                        : [],
                    [['label' => 'Semua Transaksi', 'route' => 'transactions.index', 'active' => 'transactions.index']],
                ),
            ],
            [
                'label' => 'Approval', 'icon' => 'verified', 'active' => 'approvals.*', 'can' => 'approval.view',
                'badge' => 'pending',
                'children' => [
                    ['label' => 'Inbox Pending Review', 'route' => 'approvals.index', 'active' => 'approvals.index'],
                    ['label' => 'Riwayat Approval', 'route' => 'approvals.history', 'active' => 'approvals.history'],
                ],
            ],
            [
                'label' => 'Jurnal', 'icon' => 'menu_book', 'active' => 'journals.*', 'can' => 'journal.view',
                'children' => array_values(array_filter([
                    ['label' => 'Daftar Jurnal', 'route' => 'journals.index', 'active' => 'journals.index'],
                    $user->hasPermission('journal.manage') ? ['label' => 'Jurnal Manual', 'route' => 'journals.create', 'active' => 'journals.create'] : null,
                    ['label' => 'Jurnal Pembalik', 'route' => 'journals.reversals', 'active' => 'journals.reversals'],
                    $user->hasPermission('journal.manage') ? ['label' => 'Posting Batch', 'route' => 'journals.batch', 'active' => 'journals.batch'] : null,
                ])),
            ],
            [
                'label' => 'General Ledger', 'icon' => 'account_balance', 'active' => 'gl.*', 'can' => 'gl.view',
                'children' => [
                    ['label' => 'Buku Besar per Akun', 'route' => 'gl.ledger', 'active' => 'gl.ledger'],
                    ['label' => 'Ringkasan Saldo Akun', 'route' => 'gl.summary', 'active' => 'gl.summary'],
                    ['label' => 'Trial Balance', 'route' => 'gl.trial-balance', 'active' => 'gl.trial-balance'],
                ],
            ],
            [
                'label' => 'Pengaturan', 'icon' => 'settings', 'active' => 'settings.*', 'can' => ['users.manage', 'audit.view', 'settings.manage'],
                'children' => array_values(array_filter([
                    $user->hasPermission('users.manage') ? ['label' => 'Pengguna', 'route' => 'settings.users.index', 'active' => 'settings.users.*'] : null,
                    $user->hasPermission('audit.view') ? ['label' => 'Audit Log', 'route' => 'settings.audit', 'active' => 'settings.audit'] : null,
                    $user->hasPermission('settings.manage') ? ['label' => 'Pengaturan Umum', 'route' => 'settings.general', 'active' => 'settings.general'] : null,
                ])),
            ],
        ];

        return array_values(array_filter($menus, function (array $menu) use ($user) {
            if ($menu['can'] === null) {
                return true;
            }
            foreach ((array) $menu['can'] as $permission) {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @param  array<string, mixed>  $item */
    public static function isActive(array $item): bool
    {
        if (! request()->routeIs($item['active'])) {
            return false;
        }
        if (isset($item['active_param'])) {
            [$param, $value] = $item['active_param'];

            return request()->route($param) === $value;
        }

        return true;
    }

    /** @param  array<string, mixed>  $menu */
    public static function isGroupActive(array $menu): bool
    {
        if (request()->routeIs($menu['active'])) {
            return true;
        }

        foreach ($menu['children'] ?? [] as $child) {
            if (request()->routeIs($child['active'])) {
                return true;
            }
        }

        return false;
    }
}
