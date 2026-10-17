<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Transaction;
use App\Services\AttachmentService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $service) {}

    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $maxKb = app(Settings::class)->get('attachment_max_kb');
        $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', "max:{$maxKb}"],
        ], [
            'attachments.*.mimes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachments.*.mimetypes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachments.*.max' => 'Ukuran lampiran maksimal '.number_format($maxKb / 1024, 0).' MB per berkas.',
        ]);

        foreach ($request->file('attachments') as $file) {
            $this->service->store($transaction, $file, $request->user());
        }

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $attachment->transaction);
        $this->service->delete($attachment);

        return back()->with('success', 'Lampiran dihapus.');
    }

    /** Unduh via signed URL + otorisasi; berkas dilayani dari disk privat (di luar web root). */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->transaction);

        $disk = Storage::disk($this->service->disk());
        abort_unless($disk->exists($attachment->storage_path), 404, 'Berkas lampiran tidak ditemukan.');

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return $request->boolean('inline')
            ? $disk->response($attachment->storage_path, $attachment->file_name, $headers)
            : $disk->download($attachment->storage_path, $attachment->file_name, $headers);
    }
}
