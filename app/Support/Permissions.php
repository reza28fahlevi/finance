<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\RolePermission;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses per peran yang dapat diatur Admin (menu Pengaturan → Role & Permission).
 * Disimpan di tabel role_permissions; bila tabel belum ada/kosong, memakai default Role::defaultPermissions().
 */
class Permissions
{
    /**
     * Daftar permission per kelompok menu: key => [label, keterangan].
     *
     * @var array<string, array<string, array{0:string, 1:string}>>
     */
    public const GROUPS = [
        'Master Data' => [
            'master.view' => ['Lihat Master Data', 'Chart of Accounts, Periode, Template Jurnal (read-only)'],
            'master.manage' => ['Kelola Master Data', 'Tambah/ubah COA, buat periode, ubah template jurnal'],
            'period.close' => ['Tutup Periode', 'Menutup buku periode akuntansi'],
            'period.reopen' => ['Buka Kembali Periode', 'Membuka periode CLOSED (wajib alasan)'],
        ],
        'Transaksi' => [
            'transaction.view' => ['Lihat Transaksi', 'Daftar & detail semua transaksi, ekspor CSV'],
            'transaction.manage' => ['Input Transaksi (Maker)', 'Buat, ubah, ajukan, batalkan transaksi milik sendiri'],
        ],
        'Approval' => [
            'approval.view' => ['Lihat Approval', 'Inbox Pending Review & Riwayat Approval (read-only)'],
            'approval.act' => ['Approve / Reject (Checker)', 'Memutuskan transaksi; tetap tidak bisa menyetujui transaksi sendiri'],
        ],
        'Jurnal' => [
            'journal.view' => ['Lihat Jurnal', 'Daftar jurnal & jurnal pembalik (read-only)'],
            'journal.manage' => ['Kelola & Posting Jurnal', 'Jurnal manual, posting, posting batch, jurnal pembalik'],
        ],
        'General Ledger' => [
            'gl.view' => ['Lihat General Ledger', 'Buku besar per akun, ringkasan saldo, trial balance'],
        ],
        'Pengaturan' => [
            'users.manage' => ['Kelola Pengguna', 'Tambah/ubah pengguna, peran, reset password'],
            'roles.manage' => ['Kelola Role & Permission', 'Mengatur hak akses menu per peran (halaman ini)'],
            'audit.view' => ['Lihat Audit Log', 'Riwayat perubahan data & status (read-only)'],
            'settings.manage' => ['Pengaturan Umum', 'Nama perusahaan, format nomor, ambang lampiran/approval'],
        ],
    ];

    /** Permission "kelola" otomatis menyertakan permission "lihat" yang dibutuhkan halamannya. */
    public const IMPLIES = [
        'master.manage' => ['master.view'],
        'period.close' => ['master.view'],
        'period.reopen' => ['master.view'],
        'transaction.manage' => ['transaction.view'],
        'approval.act' => ['approval.view', 'transaction.view'],
        'journal.manage' => ['journal.view'],
    ];

    /** Permission yang selalu melekat pada Admin agar tidak terkunci dari sistem. */
    public const LOCKED = [
        'admin' => ['users.manage', 'roles.manage'],
    ];

    private const CACHE_KEY = 'finance.role_permissions';

    /** @var array<string, list<string>>|null */
    private ?array $map = null;

    public function __construct(private AuditLogger $audit) {}

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(array_merge(...array_values(self::GROUPS)));
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $items) {
            if (isset($items[$permission])) {
                return $items[$permission][0];
            }
        }

        return $permission;
    }

    public function allows(Role $role, string $permission): bool
    {
        return in_array($permission, $this->forRole($role), true);
    }

    /** @return list<string> */
    public function forRole(Role $role): array
    {
        return $this->map()[$role->value] ?? [];
    }

    /** @return array<string, list<string>> */
    public function map(): array
    {
        return $this->map ??= Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('role_permissions') || RolePermission::query()->doesntExist()) {
                return self::defaults();
            }

            $stored = RolePermission::query()->get()->groupBy('role')
                ->map(fn ($rows) => $rows->pluck('permission')->values()->all())
                ->all();

            return collect(Role::cases())
                ->mapWithKeys(fn (Role $r) => [$r->value => self::withLocked($r, $stored[$r->value] ?? [])])
                ->all();
        });
    }

    /** @return array<string, list<string>> */
    public static function defaults(): array
    {
        return collect(Role::cases())
            ->mapWithKeys(fn (Role $r) => [$r->value => $r->defaultPermissions()])
            ->all();
    }

    /**
     * Simpan matriks hak akses. $matrix: [role => [permission, ...]].
     * Permission tidak dikenal diabaikan; permission turunan (IMPLIES) & terkunci (LOCKED) ditambahkan otomatis.
     *
     * @param  array<string, array<int, string>>  $matrix
     * @return array<string, array{added: list<string>, removed: list<string>}> perubahan per peran
     */
    public function sync(array $matrix): array
    {
        $valid = self::keys();
        $before = $this->map();
        $changes = [];

        DB::transaction(function () use ($matrix, $valid, $before, &$changes) {
            foreach (Role::cases() as $role) {
                $perms = array_values(array_intersect($valid, (array) ($matrix[$role->value] ?? [])));
                $perms = self::withLocked($role, self::withImplied($perms));

                $old = $before[$role->value] ?? [];
                $added = array_values(array_diff($perms, $old));
                $removed = array_values(array_diff($old, $perms));

                RolePermission::where('role', $role->value)->delete();
                RolePermission::insert(array_map(fn ($p) => [
                    'role' => $role->value, 'permission' => $p, 'created_at' => now(), 'updated_at' => now(),
                ], $perms));

                if ($added || $removed) {
                    $changes[$role->value] = ['added' => $added, 'removed' => $removed];
                }
            }

            if ($changes) {
                $this->audit->log('permissions.updated', null,
                    collect($changes)->map(fn ($c) => $c['removed'])->filter()->all(),
                    collect($changes)->map(fn ($c) => $c['added'])->filter()->all(),
                    'Perubahan hak akses: '.implode(', ', array_keys($changes)),
                );
            }
        });

        $this->flush();

        return $changes;
    }

    public function resetToDefaults(): array
    {
        return $this->sync(self::defaults());
    }

    public function flush(): void
    {
        $this->map = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @param list<string> $perms @return list<string> */
    public static function withImplied(array $perms): array
    {
        foreach ($perms as $p) {
            foreach (self::IMPLIES[$p] ?? [] as $implied) {
                $perms[] = $implied;
            }
        }

        return array_values(array_unique($perms));
    }

    /** @param list<string> $perms @return list<string> */
    private static function withLocked(Role $role, array $perms): array
    {
        return array_values(array_unique(array_merge($perms, self::LOCKED[$role->value] ?? [])));
    }

    public static function isLocked(Role $role, string $permission): bool
    {
        return in_array($permission, self::LOCKED[$role->value] ?? [], true);
    }
}
