<?php

namespace Tests\Unit;

use App\Enums\PeriodStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Services\LedgerService;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class PeriodAndLedgerTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    public function test_close_period_blocked_by_draft_journal(): void
    {
        $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('jurnal DRAFT');
        app(PeriodService::class)->close($this->currentPeriod(), $this->user('accountant'));
    }

    public function test_close_period_blocked_by_pending_transaction(): void
    {
        $this->submittedTransaction();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Pending Review/Approved');
        app(PeriodService::class)->close($this->currentPeriod(), $this->user('accountant'));
    }

    public function test_close_and_reopen_period_with_audit(): void
    {
        $service = app(PeriodService::class);
        $period = $service->close($this->currentPeriod(), $this->user('accountant'));

        $this->assertSame(PeriodStatus::Closed, $period->status);
        $this->assertSame($this->user('accountant')->id, $period->closed_by);
        $this->assertNotNull($period->closed_at);

        $reopened = $service->reopen($period, 'Koreksi audit');
        $this->assertSame(PeriodStatus::Open, $reopened->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'period.reopened', 'note' => 'Koreksi audit']);
    }

    public function test_create_period_and_duplicate_rejected(): void
    {
        $service = app(PeriodService::class);
        $code = now()->addMonthsNoOverflow(1)->format('Y-m');
        $period = $service->create($code);

        $this->assertSame($code, $period->code);
        $this->assertSame(now()->addMonthsNoOverflow(1)->endOfMonth()->toDateString(), $period->end_date->toDateString());

        $this->expectException(BusinessRuleException::class);
        $service->create($code);
    }

    public function test_trial_balance_is_balanced_after_postings(): void
    {
        $this->postedJournal([['1-1001', '1000000', 0], ['3-1000', 0, '1000000']]);
        $this->postedJournal([['5-1200', '250000', 0], ['1-1001', 0, '250000']]);
        $this->postedJournal([['1-2000', '400000', 0], ['4-1000', 0, '400000']]);

        $tb = app(LedgerService::class)->trialBalance($this->currentPeriod());

        $this->assertTrue($tb['balanced']);
        $this->assertSame('1400000.00', $tb['total_debit']); // Kas 750rb + Piutang 400rb + Beban 250rb
        $this->assertSame('1400000.00', $tb['total_credit']); // Modal 1jt + Pendapatan 400rb
        $this->assertSame($tb['mutation_debit'], $tb['mutation_credit']);
    }

    public function test_negative_balance_moves_to_opposite_side_in_trial_balance(): void
    {
        // Kas dikredit tanpa saldo → saldo negatif (normal debit) tampil di kolom kredit.
        $this->postedJournal([['5-1000', '300', 0], ['1-1003', 0, '300']]);

        $tb = app(LedgerService::class)->trialBalance($this->currentPeriod());
        $petty = $tb['rows']->first(fn ($r) => $r['account']->code === '1-1003');

        $this->assertSame('0.00', $petty['debit']);
        $this->assertSame('300.00', $petty['credit']);
        $this->assertTrue($tb['balanced']);
    }

    public function test_account_ledger_reports_opening_mutations_and_closing(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $this->postedJournal([['1-1001', '500', 0], ['3-1000', 0, '500']], $prev->start_date->toDateString());
        $this->postedJournal([['1-1001', '200', 0], ['4-1000', 0, '200']]);
        $this->postedJournal([['5-1000', '50', 0], ['1-1001', 0, '50']]);

        $data = app(LedgerService::class)->accountLedger($this->account('1-1001'), $this->currentPeriod());

        $this->assertSame('500.00', $data['opening']);
        $this->assertSame('200.00', $data['total_debit']);
        $this->assertSame('50.00', $data['total_credit']);
        $this->assertSame('650.00', $data['closing']);
        $this->assertCount(2, $data['entries']);
    }

    public function test_ledger_without_activity_uses_carry_forward(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $this->postedJournal([['1-1002', '900', 0], ['3-1000', 0, '900']], $prev->start_date->toDateString());

        $data = app(LedgerService::class)->accountLedger($this->account('1-1002'), $this->currentPeriod());
        $this->assertSame('900.00', $data['opening']);
        $this->assertSame('900.00', $data['closing']);
        $this->assertCount(0, $data['entries']);
    }

    public function test_audit_log_is_append_only(): void
    {
        $this->makeTransaction();
        $log = AuditLog::firstOrFail();

        try {
            $log->update(['action' => 'hapus-jejak']);
            $this->fail('Audit log seharusnya tidak dapat diubah.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_master_data_changes_are_audited_with_old_and_new_values(): void
    {
        $this->account('5-1000')->update(['name' => 'Beban Operasional Lain']);

        $log = AuditLog::where('action', 'updated')->where('auditable_type', Account::class)->latest('id')->firstOrFail();
        $this->assertSame('Beban Operasional Umum', $log->old_values['name']);
        $this->assertSame('Beban Operasional Lain', $log->new_values['name']);
    }
}
