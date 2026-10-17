<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Attachment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan lampiran bukti transaksi:
 * disk privat (storage/app/private), nama berkas acak, hash SHA-256, pindai antivirus opsional.
 */
class AttachmentService
{
    public const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __construct(private AuditLogger $audit) {}

    public function store(Transaction $transaction, UploadedFile $file, User $user): Attachment
    {
        if (! $transaction->isEditable()) {
            throw new BusinessRuleException('Lampiran hanya dapat ditambahkan pada transaksi Draft/Rejected.');
        }

        // Validasi MIME dari isi berkas (bukan dari ekstensi/klien).
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new BusinessRuleException("Tipe berkas {$file->getClientOriginalName()} tidak diizinkan. Hanya PDF/JPG/PNG.");
        }

        $this->scan($file);

        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        };

        $sha256 = hash_file('sha256', $file->getRealPath());
        $directory = 'attachments/'.now()->format('Y/m');
        $name = Str::random(40).'.'.$extension;

        $path = Storage::disk($this->disk())->putFileAs($directory, $file, $name);
        if ($path === false) {
            throw new BusinessRuleException('Gagal menyimpan lampiran.');
        }

        $attachment = $transaction->attachments()->create([
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'storage_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
            'uploaded_by' => $user->id,
        ]);

        $this->audit->log('attachment.uploaded', $transaction, null, [
            'attachment_id' => $attachment->id,
            'file_name' => $attachment->file_name,
            'sha256' => $sha256,
        ]);

        return $attachment;
    }

    public function delete(Attachment $attachment): void
    {
        $transaction = $attachment->transaction;
        if (! $transaction->isEditable()) {
            throw new BusinessRuleException('Lampiran hanya dapat dihapus pada transaksi Draft/Rejected.');
        }

        Storage::disk($this->disk())->delete($attachment->storage_path);
        $this->audit->log('attachment.deleted', $transaction, [
            'attachment_id' => $attachment->id,
            'file_name' => $attachment->file_name,
            'sha256' => $attachment->sha256,
        ]);
        $attachment->delete();
    }

    public function disk(): string
    {
        return config('finance.attachment.disk', 'local');
    }

    /** Pindai berkas dengan ClamAV bila diaktifkan (FINANCE_ANTIVIRUS_ENABLED). */
    private function scan(UploadedFile $file): void
    {
        if (! config('finance.antivirus.enabled')) {
            return;
        }

        $result = Process::timeout(60)->run([config('finance.antivirus.binary'), '--no-summary', $file->getRealPath()]);

        // clamscan: exit 0 = bersih, 1 = virus ditemukan, 2 = error
        if ($result->exitCode() === 1) {
            throw new BusinessRuleException("Berkas {$file->getClientOriginalName()} terdeteksi mengandung malware dan ditolak.");
        }
        if ($result->exitCode() !== 0) {
            throw new BusinessRuleException('Pemindaian antivirus gagal; berkas tidak dapat diterima. Hubungi admin.');
        }
    }
}
