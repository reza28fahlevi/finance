<?php

namespace Tests\Unit;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private JournalService $journals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
        $this->journals = app(JournalService::class);
    }

    private function lines(array $spec): array
    {
        return array_map(fn ($l) => ['account_id' => $this->account($l[0])->id, 'debit' => $l[1], 'credit' => $l[2]], $spec);
    }

    public function test_balanced_lines_are_normalized(): void
    {
        $result = $this->journals->validateLines($this->lines([
            ['5-1200', '15000000', ''],
            ['1-4000', '', '15000000.00'],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('15000000.00', $result[0]['debit']);
        $this->assertSame('0.00', $result[0]['credit']);
        $this->assertSame(2, $result[1]['line_no']);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak seimbang');

        $this->journals->validateLines($this->lines([
            ['5-1200', '15000000', 0],
            ['1-1001', 0, '14999999.99'],
        ]));
    }

    public function test_balance_check_is_exact_to_the_cent(): void
    {
        // 0.1 + 0.2 = 0.3 harus dianggap seimbang (tidak ada galat float).
        $result = $this->journals->validateLines($this->lines([
            ['5-1000', '0.10', 0],
            ['5-1300', '0.20', 0],
            ['1-1001', 0, '0.30'],
        ]));

        $this->assertCount(3, $result);
    }

    public function test_minimum_two_lines(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('minimal terdiri dari 2 baris');

        $this->journals->validateLines($this->lines([['5-1000', '100', 0]]));
    }

    public function test_line_with_both_debit_and_credit_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('debit ATAU kredit');

        $this->journals->validateLines($this->lines([
            ['5-1000', '100', '100'],
            ['1-1001', 0, '0'],
        ]));
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('negatif');

        $this->journals->validateLines($this->lines([
            ['5-1000', '-100', 0],
            ['1-1001', 0, '-100'],
        ]));
    }

    public function test_inactive_account_is_rejected(): void
    {
        $this->account('5-1400')->update(['is_active' => false]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak aktif atau bukan akun postable');

        $this->journals->validateLines($this->lines([['5-1400', '100', 0], ['1-1001', 0, '100']]));
    }

    public function test_header_non_postable_account_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->journals->validateLines($this->lines([['5-0000', '100', 0], ['1-1001', 0, '100']]));
    }

    public function test_empty_rows_are_ignored(): void
    {
        $result = $this->journals->validateLines(array_merge(
            $this->lines([['5-1000', '100', 0], ['1-1001', 0, '100']]),
            [['account_id' => '', 'debit' => '', 'credit' => '']],
        ));

        $this->assertCount(2, $result);
    }

    public function test_create_manual_journal_as_draft_with_reference_number(): void
    {
        $journal = $this->manualJournal([['5-1300', '3500000', 0], ['2-2000', 0, '3500000']]);

        $this->assertSame(JournalStatus::Draft, $journal->status);
        $this->assertSame(JournalSource::Manual, $journal->source);
        $this->assertMatchesRegularExpression('/^JV-\d{4}-\d{2}-0001$/', $journal->ref_no);
        $this->assertSame('3500000.00', $journal->totalDebit());
        $this->assertTrue($journal->isBalanced());
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal.created', 'auditable_id' => $journal->id]);
    }

    public function test_journal_in_closed_period_is_rejected(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $prev->update(['status' => 'CLOSED']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('CLOSED');

        $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']], $prev->start_date->toDateString());
    }

    public function test_journal_date_without_period_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Tidak ada periode');

        $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']], now()->addYears(3)->toDateString());
    }

    public function test_update_draft_replaces_lines(): void
    {
        $journal = $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']]);

        $this->journals->updateDraft($journal, [
            'entry_date' => now()->toDateString(),
            'description' => 'Revisi',
            'lines' => $this->lines([['5-1300', '250', 0], ['5-1400', '50', 0], ['1-1002', 0, '300']]),
        ]);

        $journal->refresh();
        $this->assertSame('Revisi', $journal->description);
        $this->assertCount(3, $journal->lines);
        $this->assertSame('300.00', $journal->totalCredit());
    }

    public function test_auto_journal_cannot_be_edited_or_deleted_manually(): void
    {
        $journal = $this->approvedJournal();

        try {
            $this->journals->updateDraft($journal, ['entry_date' => now()->toDateString(), 'description' => 'x', 'lines' => []]);
            $this->fail('Jurnal otomatis seharusnya tidak dapat diubah.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('otomatis', $e->getMessage());
        }

        $this->expectException(BusinessRuleException::class);
        $this->journals->deleteDraft($journal);
    }

    public function test_delete_manual_draft(): void
    {
        $journal = $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']]);
        $this->journals->deleteDraft($journal);

        $this->assertDatabaseMissing('journal_entries', ['id' => $journal->id]);
        $this->assertDatabaseMissing('journal_lines', ['journal_id' => $journal->id]);
    }
}
