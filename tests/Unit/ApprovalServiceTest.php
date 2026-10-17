<?php

namespace Tests\Unit;

use App\Enums\ApprovalAction;
use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private ApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
        $this->service = app(ApprovalService::class);
    }

    public function test_approve_creates_auto_journal_and_marks_journaled(): void
    {
        $trx = $this->submittedTransaction(amount: '2500000.75');

        ['transaction' => $trx, 'journal' => $journal] = $this->service->approve($trx, $this->user('manager'), 'OK');

        $this->assertSame(TransactionStatus::Journaled, $trx->status);
        $this->assertSame(JournalSource::Auto, $journal->source);
        $this->assertSame(JournalStatus::Draft, $journal->status);
        $this->assertSame($trx->id, $journal->transaction_id);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame('2500000.75', $journal->totalDebit());
        $this->assertSame($trx->debit_account_id, $journal->lines[0]->account_id);
        $this->assertSame($trx->credit_account_id, $journal->lines[1]->account_id);

        $this->assertDatabaseHas('approvals', ['transaction_id' => $trx->id, 'action' => ApprovalAction::Approve->value, 'approver_id' => $this->user('manager')->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.approved', 'auditable_id' => $trx->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.journaled', 'auditable_id' => $trx->id]);
    }

    public function test_maker_cannot_approve_own_transaction(): void
    {
        // Maker dengan peran checker (mis. role diubah setelah membuat transaksi).
        $maker = $this->user('staff');
        $trx = $this->submittedTransaction($maker);
        $maker->update(['role' => Role::Supervisor]);

        $this->assertStringContainsString('Maker ≠ Checker', $this->service->denialReason($trx, $maker->fresh()));

        $this->expectException(BusinessRuleException::class);
        $this->service->approve($trx, $maker->fresh());
    }

    public function test_non_checker_roles_cannot_decide(): void
    {
        $trx = $this->submittedTransaction();

        foreach (['staff2', 'accountant', 'auditor', 'admin'] as $alias) {
            $this->assertNotNull($this->service->denialReason($trx, $this->user($alias)), "{$alias} seharusnya tidak dapat approve");
        }

        $this->expectException(BusinessRuleException::class);
        $this->service->approve($trx, $this->user('accountant'));
    }

    public function test_inactive_checker_cannot_decide(): void
    {
        $trx = $this->submittedTransaction();
        $checker = User::factory()->supervisor()->inactive()->create();

        $this->assertNotNull($this->service->denialReason($trx, $checker));
    }

    public function test_reject_requires_reason_and_returns_to_maker(): void
    {
        $trx = $this->submittedTransaction();

        try {
            $this->service->reject($trx, $this->user('supervisor'), '   ');
            $this->fail('Reject tanpa alasan seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('wajib', $e->getMessage());
        }

        $rejected = $this->service->reject($trx, $this->user('supervisor'), 'Lampiran tidak jelas');
        $this->assertSame(TransactionStatus::Rejected, $rejected->status);
        $this->assertSame('Lampiran tidak jelas', $rejected->rejection_reason);
        $this->assertDatabaseHas('approvals', ['transaction_id' => $trx->id, 'action' => 'REJECT', 'note' => 'Lampiran tidak jelas']);
        $this->assertDatabaseMissing('journal_entries', ['transaction_id' => $trx->id]);
    }

    public function test_cannot_decide_twice(): void
    {
        $trx = $this->submittedTransaction();
        $this->service->approve($trx, $this->user('manager'));

        $this->expectException(BusinessRuleException::class);
        $this->service->reject($trx->fresh(), $this->user('supervisor'), 'Terlambat');
    }

    public function test_cannot_approve_draft(): void
    {
        $trx = $this->makeTransaction();

        $this->expectException(BusinessRuleException::class);
        $this->service->approve($trx, $this->user('manager'));
    }

    public function test_tiered_approval_requires_manager_above_threshold(): void
    {
        $this->setting('approval_tiered', true);
        $this->setting('approval_manager_threshold', '10000000');

        $small = $this->submittedTransaction(amount: '9999999.99');
        $big = $this->makeTransaction(amount: '10000000', files: [$this->fakePdf()]);
        $big = app(TransactionService::class)->submit($big, $this->user('staff'));

        $this->assertNull($this->service->denialReason($small, $this->user('supervisor')));
        $this->assertStringContainsString('Manager', $this->service->denialReason($big, $this->user('supervisor')));
        $this->assertNull($this->service->denialReason($big, $this->user('manager')));

        $this->service->approve($big, $this->user('manager'));
        $this->assertSame(TransactionStatus::Journaled, $big->fresh()->status);
    }

    public function test_tiered_disabled_lets_supervisor_approve_any_amount(): void
    {
        $this->setting('approval_tiered', false);
        $trx = $this->makeTransaction(amount: '50000000', files: [$this->fakePdf()]);
        $trx = app(TransactionService::class)->submit($trx, $this->user('staff'));

        $this->assertNull($this->service->denialReason($trx, $this->user('supervisor')));
    }

    public function test_auto_post_on_approve_setting(): void
    {
        $this->setting('auto_post_on_approve', true);
        $trx = $this->submittedTransaction(amount: '400000');

        $journal = $this->service->approve($trx, $this->user('manager'))['journal'];

        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertDatabaseHas('gl_entries', ['journal_id' => $journal->id]);
    }
}
