<?php

namespace Tests\Feature;

use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalTemplate;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class MasterDataAndSettingsTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    public function test_admin_creates_account_with_unique_code(): void
    {
        $admin = $this->user('admin');
        $data = [
            'code' => '5-1500', 'name' => 'Beban Pemasaran', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT',
            'parent_id' => $this->account('5-0000')->id, 'is_postable' => 1, 'is_active' => 1,
        ];

        $this->actingAs($admin)->post(route('master.accounts.store'), $data)->assertRedirect(route('master.accounts.index'));
        $this->assertDatabaseHas('accounts', ['code' => '5-1500', 'parent_id' => $this->account('5-0000')->id]);

        $this->actingAs($admin)->post(route('master.accounts.store'), $data)->assertSessionHasErrors('code');

        // Parent harus akun induk (non-postable)
        $this->actingAs($admin)->post(route('master.accounts.store'), array_merge($data, ['code' => '5-1600', 'parent_id' => $this->account('5-1000')->id]))
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($admin)->get(route('master.accounts.index', ['q' => 'Pemasaran']))->assertSee('5-1500');
    }

    public function test_used_account_cannot_be_deleted_only_deactivated(): void
    {
        $admin = $this->user('admin');
        $this->postedJournal([['5-1400', '100', 0], ['1-1001', 0, '100']]);
        $used = $this->account('5-1400');

        $this->actingAs($admin)->delete(route('master.accounts.destroy', $used))->assertSessionHas('error');
        $this->assertModelExists($used);

        // Kode/tipe akun yang sudah dijurnal dikunci
        $this->actingAs($admin)->put(route('master.accounts.update', $used), [
            'code' => '5-9999', 'name' => $used->name, 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_postable' => 1, 'is_active' => 1,
        ])->assertSessionHas('error');
        $this->assertSame('5-1400', $used->fresh()->code);

        $this->actingAs($admin)->patch(route('master.accounts.toggle', $used))->assertSessionHas('success');
        $this->assertFalse($used->fresh()->is_active);

        $unused = Account::factory()->create(['code' => '5-8888']);
        $this->actingAs($admin)->delete(route('master.accounts.destroy', $unused))->assertSessionHas('success');
        $this->assertModelMissing($unused);
    }

    public function test_period_create_close_and_reopen_via_http(): void
    {
        $code = now()->addMonthsNoOverflow(1)->format('Y-m');
        $this->actingAs($this->user('admin'))->post(route('master.periods.store'), ['code' => $code])->assertSessionHas('success');
        $this->actingAs($this->user('admin'))->post(route('master.periods.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->actingAs($this->user('admin'))->post(route('master.periods.store'), ['code' => '2026/13'])->assertSessionHasErrors('code');

        $period = AccountingPeriod::where('code', $code)->firstOrFail();
        $this->actingAs($this->user('accountant'))->post(route('master.periods.close', $period))->assertSessionHas('success');
        $this->assertSame(PeriodStatus::Closed, $period->fresh()->status);

        $this->actingAs($this->user('admin'))->post(route('master.periods.reopen', $period), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->user('admin'))->post(route('master.periods.reopen', $period), ['reason' => 'Koreksi akhir tahun'])->assertSessionHas('success');
        $this->assertSame(PeriodStatus::Open, $period->fresh()->status);
    }

    public function test_period_close_blocked_with_pending_items_via_http(): void
    {
        $this->submittedTransaction();

        $this->actingAs($this->user('accountant'))->post(route('master.periods.close', $this->currentPeriod()))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Pending Review'));
        $this->assertTrue($this->currentPeriod()->isOpen());
    }

    public function test_journal_template_update(): void
    {
        $this->actingAs($this->user('admin'))->put(route('master.templates.update'), [
            'templates' => [
                'CASH_IN' => ['debit_account_id' => $this->account('1-1002')->id, 'credit_account_id' => $this->account('4-2000')->id],
                'CASH_OUT' => ['debit_account_id' => $this->account('5-1000')->id, 'credit_account_id' => $this->account('1-1001')->id],
                'SALES_INV' => ['debit_account_id' => $this->account('1-2000')->id, 'credit_account_id' => $this->account('4-1000')->id],
                'PURCHASE_INV' => ['debit_account_id' => $this->account('1-3000')->id, 'credit_account_id' => $this->account('2-1000')->id],
            ],
        ])->assertSessionHas('success');

        $this->assertSame($this->account('1-1002')->id, JournalTemplate::where('transaction_type', 'CASH_IN')->value('debit_account_id'));

        // Template dipakai sebagai default form transaksi
        $this->actingAs($this->user('staff'))->get(route('transactions.create', 'penerimaan-kas'))
            ->assertSee('value="'.$this->account('1-1002')->id.'" selected', false);

        // Akun sama ditolak
        $this->actingAs($this->user('admin'))->put(route('master.templates.update'), [
            'templates' => ['CASH_IN' => ['debit_account_id' => 1, 'credit_account_id' => 1]],
        ])->assertSessionHasErrors();
    }

    public function test_general_settings_update_affects_rules(): void
    {
        $this->actingAs($this->user('admin'))->put(route('settings.general.update'), [
            'company_name' => 'PT Uji Coba',
            'journal_ref_format' => 'JU/{YY}{MM}/{SEQ:3}',
            'transaction_ref_format' => '{TYPE}/{YYYY}/{SEQ:5}',
            'attachment_required_above' => '100000',
            'attachment_max_kb' => '2048',
            'approval_tiered' => '1',
            'approval_manager_threshold' => '5000000',
        ])->assertSessionHas('success');

        $settings = app(Settings::class);
        $this->assertSame('PT Uji Coba', $settings->get('company_name'));
        $this->assertTrue($settings->get('approval_tiered'));
        $this->assertSame(2048, $settings->get('attachment_max_kb'));

        $trx = $this->makeTransaction(amount: '50000');
        $this->assertMatchesRegularExpression('#^BKK/\d{4}/00001$#', $trx->number);
        $journal = $this->manualJournal([['1-1001', '1', 0], ['3-1000', 0, '1']]);
        $this->assertMatchesRegularExpression('#^JU/\d{4}/001$#', $journal->ref_no);

        $this->actingAs($this->user('staff'))->get('/dashboard')->assertSee('PT Uji Coba');

        // Format tanpa {SEQ}/{TYPE} ditolak
        $this->actingAs($this->user('admin'))->put(route('settings.general.update'), [
            'company_name' => 'X', 'journal_ref_format' => 'JV', 'transaction_ref_format' => 'TRX-{SEQ}',
            'attachment_required_above' => '0', 'attachment_max_kb' => '100', 'approval_manager_threshold' => '0',
        ])->assertSessionHasErrors(['journal_ref_format', 'transaction_ref_format']);
    }

    public function test_user_management(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Staff Baru', 'email' => 'baru@finance.test', 'role' => 'staff',
            'password' => 'Rahasia123!', 'password_confirmation' => 'Rahasia123!', 'is_active' => 1,
        ])->assertRedirect(route('settings.users.index'));

        $user = User::where('email', 'baru@finance.test')->firstOrFail();
        $this->assertSame('staff', $user->role->value);

        $this->actingAs($admin)->put(route('settings.users.update', $user), [
            'name' => 'Staff Baru', 'email' => 'baru@finance.test', 'role' => 'auditor', 'is_active' => 0,
        ])->assertSessionHas('success');
        $this->assertSame('auditor', $user->fresh()->role->value);
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->put(route('settings.users.password', $user), ['password' => 'BaruLagi456!', 'password_confirmation' => 'BaruLagi456!'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('BaruLagi456!', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'auditable_id' => $user->id]);

        // Admin tidak dapat menonaktifkan dirinya sendiri
        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0,
        ])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);

        // Password tidak tercatat di audit log
        $this->assertDatabaseMissing('audit_logs', ['new_values->password' => $user->fresh()->password]);
    }
}
