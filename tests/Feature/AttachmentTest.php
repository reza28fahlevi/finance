<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
    }

    public function test_upload_validates_type_and_size(): void
    {
        $staff = $this->user('staff');
        $trx = $this->makeTransaction($staff);

        $this->actingAs($staff)->post(route('transactions.attachments.store', $trx), [
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');

        $this->actingAs($staff)->post(route('transactions.attachments.store', $trx), [
            'attachments' => [UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')],
        ])->assertSessionHasErrors('attachments.0');

        $this->actingAs($staff)->post(route('transactions.attachments.store', $trx), [
            'attachments' => [UploadedFile::fake()->image('nota.jpg'), $this->fakePdf('faktur.pdf')],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(2, $trx->attachments()->count());
    }

    public function test_attachment_stored_outside_web_root(): void
    {
        $trx = $this->makeTransaction(files: [$this->fakePdf()]);
        $att = $trx->attachments()->first();

        Storage::disk('local')->assertExists($att->storage_path);
        $this->assertFileDoesNotExist(public_path($att->storage_path));
        $this->assertFileDoesNotExist(public_path('storage/'.$att->storage_path));
    }

    public function test_download_requires_valid_signature_and_authorization(): void
    {
        $trx = $this->makeTransaction(files: [$this->fakePdf('bukti-sewa.pdf')]);
        $att = $trx->attachments()->first();

        // Tanpa signature → 403
        $this->actingAs($this->user('auditor'))->get(route('attachments.download', $att))->assertForbidden();

        // Signature valid → unduh
        $this->actingAs($this->user('auditor'))->get($att->downloadUrl())
            ->assertOk()->assertDownload('bukti-sewa.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');

        // Inline (lihat PDF)
        $this->actingAs($this->user('manager'))->get($att->downloadUrl(true))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Signature yang dimanipulasi → 403
        $this->actingAs($this->user('auditor'))->get(str_replace('inline=0', 'inline=1', $att->downloadUrl()))->assertForbidden();

        // Tamu → redirect login
        auth()->logout();
        $this->get($att->downloadUrl())->assertRedirect('/login');
    }

    public function test_download_link_expires(): void
    {
        $trx = $this->makeTransaction(files: [$this->fakePdf()]);
        $url = $trx->attachments()->first()->downloadUrl();

        $this->travel(config('finance.attachment.url_ttl') + 1)->minutes();

        $this->actingAs($this->user('auditor'))->get($url)->assertForbidden();
    }

    public function test_delete_attachment_only_while_editable(): void
    {
        $staff = $this->user('staff');
        $trx = $this->makeTransaction($staff, files: [$this->fakePdf(), $this->fakePdf('dua.pdf')]);
        [$first, $second] = $trx->attachments->all();

        $this->actingAs($staff)->delete(route('attachments.destroy', $first))->assertSessionHas('success');
        $this->assertModelMissing($first);
        Storage::disk('local')->assertMissing($first->storage_path);

        app(TransactionService::class)->submit($trx, $staff);
        $this->actingAs($staff)->delete(route('attachments.destroy', $second))->assertForbidden();
        $this->assertModelExists($second);
        $this->assertInstanceOf(Attachment::class, $second->fresh());
    }
}
