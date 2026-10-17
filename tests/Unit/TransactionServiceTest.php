<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedCore();
        $this->service = app(TransactionService::class);
    }

    public function test_create_draft_with_auto_number_and_audit(): void
    {
        $trx = $this->makeTransaction(type: TransactionType::CashIn, amount: '250000');

        $this->assertSame(TransactionStatus::Draft, $trx->status);
        $this->assertMatchesRegularExpression('/^BKM-\d{4}-\d{2}-0001$/', $trx->number);
        $this->assertSame($this->currentPeriod()->id, $trx->period_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.created', 'auditable_id' => $trx->id]);
    }

    public function test_numbers_are_unique_per_type(): void
    {
        $a = $this->makeTransaction(type: TransactionType::CashOut);
        $b = $this->makeTransaction(type: TransactionType::CashOut);
        $c = $this->makeTransaction(type: TransactionType::SalesInvoice, attrs: ['party_name' => 'PT X']);

        $this->assertStringEndsWith('0001', $a->number);
        $this->assertStringEndsWith('0002', $b->number);
        $this->assertStringStartsWith('INV-OUT-', $c->number);
        $this->assertStringEndsWith('0001', $c->number);
    }

    public function test_transaction_in_closed_period_is_rejected(): void
    {
        $this->currentPeriod()->update(['status' => 'CLOSED']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('CLOSED');
        $this->makeTransaction();
    }

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('lebih besar dari 0');
        $this->makeTransaction(amount: '0');
    }

    public function test_debit_and_credit_accounts_must_differ_and_be_active(): void
    {
        try {
            $this->makeTransaction(attrs: ['debit_account_id' => $this->account('1-1001')->id, 'credit_account_id' => $this->account('1-1001')->id]);
            $this->fail('Akun sama seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('tidak boleh sama', $e->getMessage());
        }

        $this->account('5-1000')->update(['is_active' => false]);
        $this->expectException(BusinessRuleException::class);
        $this->makeTransaction(attrs: ['debit_account_id' => $this->account('5-1000')->id]);
    }

    public function test_submit_moves_to_pending_and_locks_edit(): void
    {
        $trx = $this->submittedTransaction();

        $this->assertSame(TransactionStatus::PendingReview, $trx->status);
        $this->assertNotNull($trx->submitted_at);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('terkunci dari edit');
        $this->service->update($trx, ['trx_date' => now()->toDateString(), 'description' => 'x', 'amount' => '1', 'debit_account_id' => $trx->debit_account_id, 'credit_account_id' => $trx->credit_account_id], $this->user('staff'));
    }

    public function test_attachment_required_above_threshold(): void
    {
        $this->setting('attachment_required_above', '1000000');
        $trx = $this->makeTransaction(amount: '1500000');

        try {
            $this->service->submit($trx, $this->user('staff'));
            $this->fail('Submit tanpa lampiran seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('wajib memiliki minimal 1 lampiran', $e->getMessage());
        }

        $withFile = $this->makeTransaction(amount: '1500000', files: [$this->fakePdf()]);
        $this->assertSame(TransactionStatus::PendingReview, $this->service->submit($withFile, $this->user('staff'))->status);

        // Tepat di ambang tidak wajib lampiran.
        $atThreshold = $this->makeTransaction(amount: '1000000');
        $this->assertSame(TransactionStatus::PendingReview, $this->service->submit($atThreshold, $this->user('staff'))->status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $trx = $this->submittedTransaction();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak diizinkan');
        $this->service->submit($trx, $this->user('staff'));
    }

    public function test_cancel_only_from_draft(): void
    {
        $draft = $this->makeTransaction();
        $this->assertSame(TransactionStatus::Cancelled, $this->service->cancel($draft, 'dobel')->status);

        $pending = $this->submittedTransaction();
        $this->expectException(BusinessRuleException::class);
        $this->service->cancel($pending);
    }

    public function test_revising_rejected_transaction_returns_to_draft(): void
    {
        $trx = $this->submittedTransaction();
        $trx->update(['status' => TransactionStatus::Rejected, 'rejection_reason' => 'Salah nominal']);

        $updated = $this->service->update($trx, [
            'trx_date' => now()->toDateString(), 'description' => 'Diperbaiki', 'amount' => '450000',
            'debit_account_id' => $trx->debit_account_id, 'credit_account_id' => $trx->credit_account_id,
        ], $this->user('staff'));

        $this->assertSame(TransactionStatus::Draft, $updated->status);
        $this->assertSame('450000.00', $updated->amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.revised', 'auditable_id' => $trx->id]);
    }

    public function test_attachment_is_stored_privately_with_random_name_and_hash(): void
    {
        $file = $this->fakePdf('Invoice Sewa.pdf');
        $trx = $this->makeTransaction(files: [$file]);
        $att = $trx->attachments()->first();

        $this->assertSame('Invoice Sewa.pdf', $att->file_name);
        $this->assertSame('application/pdf', $att->mime_type);
        $this->assertMatchesRegularExpression('#^attachments/\d{4}/\d{2}/[A-Za-z0-9]{40}\.pdf$#', $att->storage_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($att->storage_path)), $att->sha256);
        Storage::disk('local')->assertExists($att->storage_path);
    }

    public function test_attachment_rejects_disallowed_mime_by_content(): void
    {
        // Ekstensi .pdf tetapi isinya executable Windows (header "MZ").
        $fake = $this->realUpload('malware.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00".str_repeat("\x00", 64).'This program cannot be run in DOS mode.');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak diizinkan');
        $this->makeTransaction(files: [$fake]);
    }

    public function test_real_pdf_and_png_are_accepted_by_content(): void
    {
        $png = $this->realUpload('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $pdf = $this->realUpload('nota.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $trx = $this->makeTransaction(files: [$png, $pdf]);

        $this->assertEqualsCanonicalizing(['image/png', 'application/pdf'], $trx->attachments()->pluck('mime_type')->all());
    }

    public function test_period_is_derived_from_transaction_date(): void
    {
        $prev = AccountingPeriod::forDate(now()->subMonthNoOverflow()->startOfMonth());
        $trx = $this->makeTransaction(attrs: ['trx_date' => $prev->start_date->toDateString()]);

        $this->assertSame($prev->id, $trx->period_id);
    }
}
