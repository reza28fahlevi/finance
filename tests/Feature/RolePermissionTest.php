<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    /** Matriks aktif saat ini, dengan modifikasi untuk satu peran. */
    private function matrixWith(string $role, array $add = [], array $remove = []): array
    {
        $matrix = app(Permissions::class)->map();
        $matrix[$role] = array_values(array_diff(array_merge($matrix[$role], $add), $remove));

        return $matrix;
    }

    public function test_defaults_match_design_matrix_after_migration(): void
    {
        $this->assertSame(Permissions::defaults(), app(Permissions::class)->map());
    }

    public function test_admin_sees_permission_matrix(): void
    {
        $this->actingAs($this->user('admin'))->get(route('settings.roles'))
            ->assertOk()->assertSee('Role &amp; Permission', false)->assertSee('Input Transaksi (Maker)')->assertSee('Staff Finance (Maker)');

        $this->actingAs($this->user('admin'))->get('/dashboard')->assertSee('Role &amp; Permission', false);
    }

    public function test_granting_permission_opens_menu_for_role(): void
    {
        $staff = $this->user('staff');
        $this->actingAs($staff)->get(route('gl.ledger'))->assertForbidden();

        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles.update'), ['permissions' => $this->matrixWith('staff', add: ['gl.view'])])
            ->assertSessionHas('success');

        $this->actingAs($staff->fresh())->get(route('gl.ledger'))->assertOk();
        $this->actingAs($staff->fresh())->get('/dashboard')->assertSee('General Ledger');
        $this->assertDatabaseHas('role_permissions', ['role' => 'staff', 'permission' => 'gl.view']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.updated']);
    }

    public function test_revoking_permission_closes_menu_and_endpoint(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles.update'), ['permissions' => $this->matrixWith('staff', remove: ['transaction.manage'])]);

        $staff = $this->user('staff');
        $this->actingAs($staff)->get(route('transactions.create', 'pengeluaran-kas'))->assertForbidden();
        $this->actingAs($staff)->post(route('transactions.store', 'pengeluaran-kas'), [])->assertForbidden();
        $this->actingAs($staff)->get('/dashboard')->assertDontSee('Pengeluaran Kas');
    }

    public function test_manage_permission_implies_view_permission(): void
    {
        $matrix = $this->matrixWith('auditor', add: ['journal.manage'], remove: ['journal.view']);
        $this->actingAs($this->user('admin'))->put(route('settings.roles.update'), ['permissions' => $matrix]);

        $perms = app(Permissions::class)->forRole(Role::Auditor);
        $this->assertContains('journal.manage', $perms);
        $this->assertContains('journal.view', $perms);
    }

    public function test_admin_cannot_lock_itself_out(): void
    {
        $matrix = $this->matrixWith('admin', remove: ['roles.manage', 'users.manage']);
        $this->actingAs($this->user('admin'))->put(route('settings.roles.update'), ['permissions' => $matrix]);

        $this->actingAs($this->user('admin'))->get(route('settings.roles'))->assertOk();
        $this->actingAs($this->user('admin'))->get(route('settings.users.index'))->assertOk();
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles.update'), ['permissions' => ['staff' => ['hack.everything']]])
            ->assertSessionHasErrors();
    }

    public function test_business_rules_still_apply_when_maker_gets_approval_permission(): void
    {
        // Staff diberi hak approve, tetapi tetap tidak boleh menyetujui transaksinya sendiri.
        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles.update'), ['permissions' => $this->matrixWith('staff', add: ['approval.act'])]);

        $own = $this->submittedTransaction($this->user('staff'));
        $other = $this->submittedTransaction($this->user('staff2'));

        $this->actingAs($this->user('staff'))->post(route('approvals.approve', $own))->assertForbidden();
        $this->actingAs($this->user('staff'))->post(route('approvals.approve', $other))->assertSessionHas('success');
    }

    public function test_reset_to_defaults(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles.update'), ['permissions' => $this->matrixWith('staff', add: ['gl.view', 'audit.view'])]);
        $this->assertTrue(app(Permissions::class)->allows(Role::Staff, 'gl.view'));

        $this->actingAs($this->user('admin'))->post(route('settings.roles.reset'))->assertSessionHas('success');

        $this->assertSame(Permissions::defaults(), app(Permissions::class)->map());
    }

    public function test_only_role_managers_can_change_permissions(): void
    {
        foreach (['staff', 'manager', 'accountant', 'auditor'] as $alias) {
            $this->actingAs($this->user($alias))->get(route('settings.roles'))->assertForbidden();
            $this->actingAs($this->user($alias))->put(route('settings.roles.update'), ['permissions' => []])->assertForbidden();
        }
    }
}
