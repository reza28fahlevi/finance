<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

/**
 * Uji hak akses per peran sesuai Matriks Akses Menu (RANCANGAN.md 10.4).
 * Setiap baris: [halaman, admin, staff, supervisor, manager, accountant, auditor] (true = boleh akses).
 */
class AccessControlTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private const ROLES = ['admin', 'staff', 'supervisor', 'manager', 'accountant', 'auditor'];

    public static function matrix(): array
    {
        //                                                  adm    stf    spv    mgr    akt    aud
        return [
            'Dashboard' => ['/dashboard',                    true,  true,  true,  true,  true,  true],
            'COA (lihat)' => ['/master/accounts',            true,  true,  true,  true,  true,  true],
            'COA (tambah)' => ['/master/accounts/create',    true,  false, false, false, false, false],
            'Periode' => ['/master/periods',                 true,  true,  true,  true,  true,  true],
            'Template jurnal' => ['/master/templates',       true,  true,  true,  true,  true,  true],
            'Semua transaksi' => ['/transactions',           true,  true,  true,  true,  true,  true],
            'Input transaksi' => ['/transactions/create/pengeluaran-kas', false, true, false, false, false, false],
            'Inbox approval' => ['/approvals',               false, false, true,  true,  false, true],
            'Riwayat approval' => ['/approvals/history',     false, false, true,  true,  false, true],
            'Daftar jurnal' => ['/journals',                 true,  false, true,  true,  true,  true],
            'Jurnal manual' => ['/journals/create',          false, false, false, false, true,  false],
            'Jurnal pembalik' => ['/journals/reversals',     true,  false, true,  true,  true,  true],
            'Posting batch' => ['/journals/batch',           false, false, false, false, true,  false],
            'Buku besar' => ['/gl/ledger',                   true,  false, true,  true,  true,  true],
            'Ringkasan saldo' => ['/gl/summary',             true,  false, true,  true,  true,  true],
            'Trial balance' => ['/gl/trial-balance',         true,  false, true,  true,  true,  true],
            'Pengguna' => ['/settings/users',                true,  false, false, false, false, false],
            'Audit log' => ['/settings/audit-logs',          true,  false, false, false, false, true],
            'Pengaturan umum' => ['/settings/general',       true,  false, false, false, false, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_menu_access_matrix(string $uri, bool ...$allowed): void
    {
        $this->seedCore();

        foreach (self::ROLES as $i => $alias) {
            $response = $this->actingAs($this->user($alias))->get($uri);
            $allowed[$i]
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_sidebar_menu_follows_role(): void
    {
        $this->seedCore();

        $this->actingAs($this->user('staff'))->get('/dashboard')
            ->assertSee('Penerimaan Kas')->assertDontSee('General Ledger')->assertDontSee('Audit Log');

        $this->actingAs($this->user('auditor'))->get('/dashboard')
            ->assertSee('Audit Log')->assertDontSee('Pengaturan Umum')->assertDontSee('Posting Batch');

        $this->actingAs($this->user('admin'))->get('/dashboard')
            ->assertSee('Pengguna')->assertSee('Pengaturan Umum')->assertDontSee('Inbox Pending Review');
    }

    public function test_write_endpoints_are_protected(): void
    {
        $this->seedCore();
        $trx = $this->submittedTransaction();
        $journal = $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);

        // Staff tidak boleh approve / posting / kelola master
        $staff = $this->user('staff2');
        $this->actingAs($staff)->post(route('approvals.approve', $trx))->assertForbidden();
        $this->actingAs($staff)->post(route('journals.post', $journal))->assertForbidden();
        $this->actingAs($staff)->post(route('master.accounts.store'), [])->assertForbidden();

        // Auditor read-only
        $auditor = $this->user('auditor');
        $this->actingAs($auditor)->post(route('approvals.reject', $trx), ['note' => 'tidak boleh'])->assertForbidden();
        $this->actingAs($auditor)->post(route('journals.batch.post'), ['journal_ids' => [$journal->id]])->assertForbidden();
        $this->actingAs($auditor)->put(route('settings.general.update'), [])->assertForbidden();

        // Manager tidak boleh posting jurnal / tutup periode
        $manager = $this->user('manager');
        $this->actingAs($manager)->post(route('journals.post', $journal))->assertForbidden();
        $this->actingAs($manager)->post(route('master.periods.close', $this->currentPeriod()))->assertForbidden();

        // Akuntan boleh tutup periode tetapi tidak membuka kembali
        $this->actingAs($this->user('accountant'))->post(route('master.periods.reopen', $this->currentPeriod()), ['reason' => 'coba buka'])->assertForbidden();

        $this->assertSame('PENDING_REVIEW', $trx->fresh()->status->value);
        $this->assertSame('DRAFT', $journal->fresh()->status->value);
    }

    public function test_staff_cannot_edit_other_makers_transaction(): void
    {
        $this->seedCore();
        $trx = $this->makeTransaction($this->user('staff'));

        $this->actingAs($this->user('staff2'))->get(route('transactions.edit', $trx))->assertForbidden();
        $this->actingAs($this->user('staff2'))->post(route('transactions.submit', $trx))->assertForbidden();
        $this->actingAs($this->user('staff'))->get(route('transactions.edit', $trx))->assertOk();
    }
}
