<?php

namespace Tests\Unit;

use App\Enums\JournalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountBalance;
use App\Models\AccountingPeriod;
use App\Models\GlEntry;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Services\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class PostingServiceTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private PostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
        $this->posting = app(PostingService::class);
    }

    private function balance(string $code, ?AccountingPeriod $period = null): ?AccountBalance
    {
        return AccountBalance::where('account_id', $this->account($code)->id)
            ->where('period_id', ($period ?? $this->currentPeriod())->id)
            ->first();
    }

    public function test_posting_updates_balances_ledger_and_status(): void
    {
        $journal = $this->manualJournal([['1-1001', '1000000', 0], ['3-1000', 0, '1000000']]);
        $this->posting->post($journal, $this->user('accountant'));

        $journal->refresh();
        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertNotNull($journal->posted_at);
        $this->assertSame($this->user('accountant')->id, $journal->posted_by);

        // Akun saldo normal debit: akhir = awal + debit − kredit
        $kas = $this->balance('1-1001');
        $this->assertSame('1000000.00', $kas->total_debit);
        $this->assertSame('1000000.00', $kas->closing_balance);

        // Akun saldo normal kredit: akhir = awal + kredit − debit
        $modal = $this->balance('3-1000');
        $this->assertSame('1000000.00', $modal->total_credit);
        $this->assertSame('1000000.00', $modal->closing_balance);

        $this->assertSame(2, GlEntry::where('journal_id', $journal->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal.posted', 'auditable_id' => $journal->id]);
    }

    public function test_running_balance_per_account(): void
    {
        $this->postedJournal([['1-1001', '1000000', 0], ['3-1000', 0, '1000000']]);
        $this->postedJournal([['5-1000', '250000', 0], ['1-1001', 0, '250000']]);
        $this->postedJournal([['1-1001', '100000.50', 0], ['4-1000', 0, '100000.50']]);

        $running = GlEntry::where('account_id', $this->account('1-1001')->id)->orderBy('id')->pluck('running_balance')->all();
        $this->assertSame(['1000000.00', '750000.00', '850000.50'], $running);
        $this->assertSame('850000.50', $this->balance('1-1001')->closing_balance);
    }

    public function test_posting_is_idempotent(): void
    {
        $journal = $this->postedJournal([['1-1001', '500', 0], ['3-1000', 0, '500']]);

        try {
            $this->posting->post($journal, $this->user('accountant'));
            $this->fail('Posting ulang seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('tidak dapat diposting ulang', $e->getMessage());
        }

        $this->assertSame('500.00', $this->balance('1-1001')->closing_balance);
        $this->assertSame(2, GlEntry::where('journal_id', $journal->id)->count());
    }

    public function test_failure_mid_posting_rolls_back_everything(): void
    {
        $journal = $this->manualJournal([['1-1001', '700', 0], ['5-1400', '300', 0], ['3-1000', 0, '1000']]);
        // Akun dinonaktifkan setelah jurnal dibuat → validasi ulang saat posting gagal.
        $this->account('5-1400')->update(['is_active' => false]);

        try {
            $this->posting->post($journal, $this->user('accountant'));
            $this->fail('Posting seharusnya gagal.');
        } catch (BusinessRuleException) {
        }

        $this->assertSame(JournalStatus::Draft, $journal->fresh()->status);
        $this->assertSame(0, GlEntry::count());
        $this->assertSame(0, AccountBalance::count());
    }

    public function test_database_error_mid_posting_rolls_back(): void
    {
        $journal = $this->manualJournal([['1-1001', '700', 0], ['3-1000', 0, '700']]);

        // Paksa error pada saat menulis gl_entries baris kedua.
        $count = 0;
        GlEntry::creating(function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('Simulasi kegagalan DB');
            }
        });

        try {
            $this->posting->post($journal, $this->user('accountant'));
            $this->fail('Seharusnya melempar exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulasi kegagalan DB', $e->getMessage());
        }

        $this->assertSame(JournalStatus::Draft, $journal->fresh()->status);
        $this->assertSame(0, GlEntry::count());
        $this->assertSame(0, AccountBalance::count());
    }

    public function test_posting_in_closed_period_is_rejected(): void
    {
        $journal = $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);
        $this->currentPeriod()->update(['status' => 'CLOSED']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('CLOSED');
        $this->posting->post($journal, $this->user('accountant'));
    }

    public function test_opening_balance_carries_forward_from_previous_period(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $this->postedJournal([['1-1001', '800', 0], ['3-1000', 0, '800']], $prev->start_date->toDateString());
        $this->postedJournal([['5-1000', '300', 0], ['1-1001', 0, '300']]);

        $kas = $this->balance('1-1001');
        $this->assertSame('800.00', $kas->opening_balance);
        $this->assertSame('500.00', $kas->closing_balance);
    }

    public function test_posting_to_earlier_open_period_propagates_to_later_periods(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $this->postedJournal([['1-1001', '1000', 0], ['3-1000', 0, '1000']]); // periode berjalan dulu
        $this->postedJournal([['1-1001', '200', 0], ['3-1000', 0, '200']], $prev->start_date->toDateString()); // lalu periode lalu

        $current = $this->balance('1-1001');
        $this->assertSame('200.00', $current->opening_balance);
        $this->assertSame('1200.00', $current->closing_balance);
        $this->assertSame('1200.00', GlEntry::where('account_id', $this->account('1-1001')->id)->where('period_id', $this->currentPeriod()->id)->value('running_balance'));
    }

    public function test_posted_journal_is_immutable(): void
    {
        $journal = $this->postedJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);

        $this->expectException(LogicException::class);
        $journal->update(['description' => 'diubah']);
    }

    public function test_posted_journal_cannot_be_deleted(): void
    {
        $journal = $this->postedJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);

        $this->expectException(LogicException::class);
        $journal->delete();
    }

    public function test_batch_posting_reports_per_journal_result(): void
    {
        $ok = $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);
        $posted = $this->postedJournal([['1-1001', '50', 0], ['3-1000', 0, '50']]);
        $bad = $this->manualJournal([['5-1400', '20', 0], ['1-1001', 0, '20']]);
        $this->account('5-1400')->update(['is_active' => false]);

        $results = $this->posting->postBatch([$ok->id, $posted->id, $bad->id, 999999], $this->user('accountant'));

        $this->assertSame([true, false, false, false], array_column($results, 'success'));
        $this->assertSame(JournalStatus::Posted, $ok->fresh()->status);
        $this->assertSame(JournalStatus::Draft, $bad->fresh()->status);
        $this->assertSame('150.00', $this->balance('1-1001')->closing_balance);
    }

    public function test_reversal_swaps_lines_posts_and_marks_original_reversed(): void
    {
        $original = $this->postedJournal([['5-1200', '15000000', 0], ['1-1002', 0, '15000000']]);

        $reversal = app(JournalService::class)->reverse($original, $this->user('accountant'), now()->toDateString(), 'Salah akun', $this->posting);

        $this->assertSame(JournalStatus::Reversed, $original->fresh()->status);
        $this->assertSame(JournalStatus::Posted, $reversal->status);
        $this->assertSame($original->id, $reversal->reversal_of_id);
        $line = $reversal->lines()->where('account_id', $this->account('5-1200')->id)->first();
        $this->assertSame('15000000.00', $line->credit);

        // Saldo kembali nol setelah dibalik.
        $this->assertSame('0.00', $this->balance('5-1200')->closing_balance);
        $this->assertSame('0.00', $this->balance('1-1002')->closing_balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal.reversed', 'auditable_id' => $original->id]);
    }

    public function test_cannot_reverse_twice_or_reverse_draft(): void
    {
        $original = $this->postedJournal([['5-1200', '100', 0], ['1-1002', 0, '100']]);
        $service = app(JournalService::class);
        $service->reverse($original, $this->user('accountant'), now()->toDateString(), 'Koreksi', $this->posting);

        try {
            $service->reverse(JournalEntry::find($original->id), $this->user('accountant'), now()->toDateString(), 'Lagi', $this->posting);
            $this->fail('Pembalikan kedua seharusnya ditolak.');
        } catch (BusinessRuleException) {
        }

        $draft = $this->manualJournal([['5-1200', '100', 0], ['1-1002', 0, '100']]);
        $this->expectException(BusinessRuleException::class);
        $service->reverse($draft, $this->user('accountant'), now()->toDateString(), 'Draft', $this->posting);
    }
}
