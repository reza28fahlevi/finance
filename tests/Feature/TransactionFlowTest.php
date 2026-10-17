<?php

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

/**
 * Alur end-to-end via HTTP: login per peran → input transaksi + lampiran → submit →
 * approve/reject → jurnal otomatis → posting → buku besar & trial balance.
 */
class TransactionFlowTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    public function test_full_flow_from_input_to_general_ledger(): void
    {
        $staff = $this->user('staff');
        $manager = $this->user('manager');
        $accountant = $this->user('accountant');
        $expense = $this->account('5-1200');
        $bank = $this->account('1-1002');

        // 1. Staff input Pengeluaran Kas + lampiran, langsung ajukan review
        $this->actingAs($staff)->get(route('transactions.create', 'pengeluaran-kas'))->assertOk()->assertSee('Pengeluaran Kas');
        $response = $this->actingAs($staff)->post(route('transactions.store', 'pengeluaran-kas'), [
            'trx_date' => now()->toDateString(),
            'description' => 'Pembayaran sewa kantor Q4',
            'amount' => '45.000.000',
            'debit_account_id' => $expense->id,
            'credit_account_id' => $bank->id,
            'party_name' => 'PT Graha Sentosa',
            'attachments' => [$this->fakePdf('Invoice_Sewa.pdf')],
            'action' => 'submit',
        ]);

        $trx = Transaction::firstOrFail();
        $response->assertRedirect(route('transactions.show', $trx))->assertSessionHas('success');
        $this->assertSame(TransactionStatus::PendingReview, $trx->status);
        $this->assertSame('45000000.00', $trx->amount);
        $this->assertCount(1, $trx->attachments);

        // 2. Muncul di inbox manager & dashboard
        $this->actingAs($manager)->get(route('approvals.index'))->assertOk()->assertSee($trx->number)->assertSee('Setujui');
        $this->actingAs($manager)->get(route('dashboard'))->assertSee($trx->number);

        // 3. Manager approve → jurnal otomatis DRAFT
        $this->actingAs($manager)->post(route('approvals.approve', $trx), ['note' => 'Sesuai kontrak'])
            ->assertRedirect(route('approvals.index'))->assertSessionHas('success');

        $trx->refresh();
        $this->assertSame(TransactionStatus::Journaled, $trx->status);
        $journal = JournalEntry::where('transaction_id', $trx->id)->firstOrFail();
        $this->assertSame(JournalStatus::Draft, $journal->status);

        // 4. Akuntan posting via Posting Batch
        $this->actingAs($accountant)->get(route('journals.batch'))->assertOk()->assertSee($journal->ref_no);
        $this->actingAs($accountant)->post(route('journals.batch.post'), ['journal_ids' => [$journal->id]])
            ->assertRedirect(route('journals.batch'))->assertSessionHas('batch_results');
        $this->assertSame(JournalStatus::Posted, $journal->fresh()->status);

        // 5. Buku besar & trial balance
        $period = $this->currentPeriod();
        $this->actingAs($accountant)->get(route('gl.ledger', ['account' => $bank->id, 'period' => $period->id]))
            ->assertOk()->assertSee($journal->ref_no)->assertSee('45.000.000');
        $this->assertSame('-45000000.00', AccountBalance::where('account_id', $bank->id)->where('period_id', $period->id)->value('closing_balance'));
        $this->assertSame('45000000.00', AccountBalance::where('account_id', $expense->id)->where('period_id', $period->id)->value('closing_balance'));

        $this->actingAs($manager)->get(route('gl.trial-balance', ['period' => $period->id]))
            ->assertOk()->assertSee('Trial Balance Seimbang');
        $this->actingAs($this->user('auditor'))->get(route('gl.summary', ['period' => $period->id]))->assertOk()->assertSee('5-1200');

        // 6. Jejak audit lengkap
        $actions = AuditLog::where('auditable_type', Transaction::class)->where('auditable_id', $trx->id)->pluck('action')->all();
        foreach (['transaction.created', 'attachment.uploaded', 'transaction.submitted', 'transaction.approved', 'transaction.journaled'] as $expected) {
            $this->assertContains($expected, $actions);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal.posted', 'auditable_id' => $journal->id, 'user_id' => $accountant->id]);
        $this->actingAs($this->user('auditor'))->get(route('settings.audit'))->assertOk()->assertSee('transaction.approved');
    }

    public function test_reject_and_revise_flow(): void
    {
        $staff = $this->user('staff');
        $supervisor = $this->user('supervisor');
        $trx = $this->submittedTransaction($staff, amount: '750000');

        // Reject tanpa alasan → validasi gagal
        $this->actingAs($supervisor)->from(route('approvals.index'))->post(route('approvals.reject', $trx), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertSame(TransactionStatus::PendingReview, $trx->fresh()->status);

        // Reject dengan alasan
        $this->actingAs($supervisor)->post(route('approvals.reject', $trx), ['note' => 'Nominal tidak sesuai nota'])->assertSessionHas('success');
        $this->assertSame(TransactionStatus::Rejected, $trx->fresh()->status);
        $this->actingAs($staff)->get(route('transactions.show', $trx))->assertSee('Nominal tidak sesuai nota')->assertSee('Revisi');

        // Staff revisi → kembali Draft lalu ajukan ulang
        $this->actingAs($staff)->put(route('transactions.update', $trx), [
            'trx_date' => now()->toDateString(), 'description' => 'Sudah dikoreksi', 'amount' => '700000',
            'debit_account_id' => $trx->debit_account_id, 'credit_account_id' => $trx->credit_account_id, 'action' => 'submit',
        ])->assertRedirect(route('transactions.show', $trx));

        $trx->refresh();
        $this->assertSame(TransactionStatus::PendingReview, $trx->status);
        $this->assertSame('700000.00', $trx->amount);

        // Approve setelah revisi
        $this->actingAs($supervisor)->post(route('approvals.approve', $trx))->assertSessionHas('success');
        $this->assertSame(TransactionStatus::Journaled, $trx->fresh()->status);
        $this->assertSame(2, Approval::where('transaction_id', $trx->id)->count());
    }

    public function test_maker_cannot_approve_own_transaction_via_http(): void
    {
        $maker = $this->user('staff');
        $trx = $this->submittedTransaction($maker);
        $maker->update(['role' => 'supervisor']);

        $this->actingAs($maker->fresh())->post(route('approvals.approve', $trx))->assertForbidden();
        $this->actingAs($maker->fresh())->get(route('approvals.index', ['id' => $trx->id]))->assertSee('Maker ≠ Checker');
        $this->assertSame(TransactionStatus::PendingReview, $trx->fresh()->status);
    }

    public function test_submit_without_required_attachment_keeps_draft_with_message(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)->post(route('transactions.store', 'pengeluaran-kas'), [
            'trx_date' => now()->toDateString(), 'description' => 'Tanpa bukti', 'amount' => '5000000',
            'debit_account_id' => $this->account('5-1000')->id, 'credit_account_id' => $this->account('1-1001')->id,
            'action' => 'submit',
        ])->assertSessionHas('error');

        $trx = Transaction::firstOrFail();
        $this->assertSame(TransactionStatus::Draft, $trx->status);
    }

    public function test_validation_errors_on_transaction_form(): void
    {
        $staff = $this->user('staff');
        $kas = $this->account('1-1001');

        $this->actingAs($staff)->post(route('transactions.store', 'penerimaan-kas'), [
            'trx_date' => '', 'description' => '', 'amount' => '-5',
            'debit_account_id' => $kas->id, 'credit_account_id' => $kas->id,
        ])->assertSessionHasErrors(['trx_date', 'description', 'amount', 'credit_account_id']);

        // Akun header (non-postable) ditolak
        $this->actingAs($staff)->post(route('transactions.store', 'penerimaan-kas'), [
            'trx_date' => now()->toDateString(), 'description' => 'x', 'amount' => '100',
            'debit_account_id' => $this->account('1-0000')->id, 'credit_account_id' => $this->account('4-1000')->id,
        ])->assertSessionHasErrors('debit_account_id');

        $this->assertSame(0, Transaction::count());
        $this->actingAs($staff)->get('/transactions/create/tidak-ada')->assertNotFound();
    }

    public function test_transaction_in_closed_period_rejected_via_http(): void
    {
        $this->currentPeriod()->update(['status' => 'CLOSED']);

        $this->actingAs($this->user('staff'))->from(route('transactions.create', 'penerimaan-kas'))
            ->post(route('transactions.store', 'penerimaan-kas'), [
                'trx_date' => now()->toDateString(), 'description' => 'Periode tutup', 'amount' => '100',
                'debit_account_id' => $this->account('1-1001')->id, 'credit_account_id' => $this->account('4-1000')->id,
            ])->assertRedirect(route('transactions.create', 'penerimaan-kas'))->assertSessionHas('error');

        $this->assertSame(0, Transaction::count());
    }

    public function test_cancel_draft_and_filters_and_csv_export(): void
    {
        $staff = $this->user('staff');
        $draft = $this->makeTransaction($staff, amount: '123000', attrs: ['description' => 'Untuk dibatalkan']);
        $pending = $this->submittedTransaction($staff, amount: '456000');

        $this->actingAs($staff)->post(route('transactions.cancel', $draft), ['reason' => 'dobel'])->assertSessionHas('success');
        $this->assertSame(TransactionStatus::Cancelled, $draft->fresh()->status);

        $this->actingAs($staff)->get(route('transactions.index', ['status' => 'CANCELLED']))
            ->assertOk()->assertSee($draft->number)->assertDontSee($pending->number);
        $this->actingAs($staff)->get(route('transactions.index', ['q' => 'dibatalkan']))->assertSee($draft->number);

        $csv = $this->actingAs($staff)->get(route('transactions.export'));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($draft->number, $csv->streamedContent());
    }

    public function test_auto_post_on_approve_updates_ledger_immediately(): void
    {
        $this->setting('auto_post_on_approve', true);
        $trx = $this->submittedTransaction(amount: '300000');

        $this->actingAs($this->user('manager'))->post(route('approvals.approve', $trx))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'langsung diposting'));

        $this->assertSame(JournalStatus::Posted, $trx->fresh()->journal->status);
    }
}
