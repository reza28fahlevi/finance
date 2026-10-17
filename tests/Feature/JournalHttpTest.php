<?php

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class JournalHttpTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    private function payload(array $lines, string $action = 'draft'): array
    {
        return [
            'entry_date' => now()->toDateString(),
            'description' => 'Penyesuaian beban sewa dibayar dimuka',
            'action' => $action,
            'lines' => array_map(fn ($l) => ['account_id' => $this->account($l[0])->id, 'description' => 'baris', 'debit' => $l[1], 'credit' => $l[2]], $lines),
        ];
    }

    public function test_manual_journal_form_renders_with_balance_indicator(): void
    {
        $this->actingAs($this->user('accountant'))->get(route('journals.create'))
            ->assertOk()->assertSee('Entri Jurnal Manual')->assertSee('Validasi Integritas Ledger')->assertSee('journalForm', false);
    }

    public function test_unbalanced_manual_journal_is_rejected(): void
    {
        $this->actingAs($this->user('accountant'))->from(route('journals.create'))
            ->post(route('journals.store'), $this->payload([['5-1200', '15000000', ''], ['1-4000', '', '14000000']]))
            ->assertRedirect(route('journals.create'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak seimbang'));

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_save_and_post_manual_journal(): void
    {
        $accountant = $this->user('accountant');
        $this->actingAs($accountant)->post(route('journals.store'), $this->payload([['5-1200', '15000000', ''], ['1-4000', '', '15000000']], 'post'))
            ->assertSessionHas('success');

        $journal = JournalEntry::firstOrFail();
        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->actingAs($accountant)->get(route('journals.show', $journal))->assertOk()->assertSee('Seimbang (Debit = Kredit)')->assertSee('immutable');
    }

    public function test_edit_and_delete_draft_but_not_posted(): void
    {
        $accountant = $this->user('accountant');
        $journal = $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']]);

        $this->actingAs($accountant)->get(route('journals.edit', $journal))->assertOk();
        $this->actingAs($accountant)->put(route('journals.update', $journal), $this->payload([['5-1000', '200', ''], ['1-1001', '', '200']]))->assertSessionHas('success');
        $this->assertSame('200.00', $journal->fresh()->totalDebit());

        $posted = $this->postedJournal([['5-1000', '50', 0], ['1-1001', 0, '50']]);
        $this->actingAs($accountant)->get(route('journals.edit', $posted))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($accountant)->put(route('journals.update', $posted), $this->payload([['5-1000', '1', ''], ['1-1001', '', '1']]))->assertSessionHas('error');
        $this->actingAs($accountant)->delete(route('journals.destroy', $posted))->assertSessionHas('error');
        $this->assertSame('50.00', $posted->fresh()->totalDebit());

        $this->actingAs($accountant)->delete(route('journals.destroy', $journal))->assertRedirect(route('journals.index'));
        $this->assertModelMissing($journal);
    }

    public function test_reversal_via_http(): void
    {
        $accountant = $this->user('accountant');
        $original = $this->postedJournal([['5-1200', '1000000', 0], ['1-1002', 0, '1000000']]);

        $this->actingAs($accountant)->get(route('journals.reversals'))->assertOk()->assertSee($original->ref_no);

        $this->actingAs($accountant)->post(route('journals.reverse', $original), ['entry_date' => now()->toDateString(), 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($accountant)->post(route('journals.reverse', $original), ['entry_date' => now()->toDateString(), 'reason' => 'Salah akun beban'])
            ->assertSessionHas('success');

        $this->assertSame(JournalStatus::Reversed, $original->fresh()->status);
        $reversal = JournalEntry::where('reversal_of_id', $original->id)->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $reversal->status);
        $this->actingAs($this->user('auditor'))->get(route('journals.reversals'))->assertSee($reversal->ref_no);
    }

    public function test_batch_posting_shows_per_journal_results(): void
    {
        $accountant = $this->user('accountant');
        $a = $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);
        $b = $this->approvedJournal('250000');

        $this->actingAs($accountant)->post(route('journals.batch.post'), ['journal_ids' => []])->assertSessionHasErrors('journal_ids');

        $this->actingAs($accountant)->post(route('journals.batch.post'), ['journal_ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 berhasil, 0 gagal'));

        $this->assertSame(JournalStatus::Posted, $a->fresh()->status);
        $this->assertSame(JournalStatus::Posted, $b->fresh()->status);

        // Posting ulang → gagal (idempotent)
        $this->actingAs($accountant)->post(route('journals.batch.post'), ['journal_ids' => [$a->id]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '0 berhasil, 1 gagal'));
    }

    public function test_journal_list_filters(): void
    {
        $draft = $this->manualJournal([['1-1001', '100', 0], ['3-1000', 0, '100']]);
        $posted = $this->postedJournal([['1-1001', '200', 0], ['3-1000', 0, '200']]);

        $this->actingAs($this->user('manager'))->get(route('journals.index', ['status' => 'POSTED']))
            ->assertOk()->assertSee($posted->ref_no)->assertDontSee($draft->ref_no);
    }

    public function test_scheduled_batch_posting_command(): void
    {
        $j = $this->approvedJournal('100000');

        $this->artisan('finance:post-batch')->expectsOutputToContain($j->ref_no)->assertExitCode(0);
        $this->assertSame(JournalStatus::Posted, $j->fresh()->status);

        $this->artisan('finance:post-batch')->expectsOutput('Tidak ada jurnal DRAFT untuk diposting.')->assertExitCode(0);
    }
}
