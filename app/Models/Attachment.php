<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

#[Fillable(['transaction_id', 'file_name', 'storage_path', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'])]
class Attachment extends Model
{
    use HasFactory;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** URL unduh bertoken (signed) yang kedaluwarsa. */
    public function downloadUrl(bool $inline = false): string
    {
        return URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(config('finance.attachment.url_ttl')),
            ['attachment' => $this->id, 'inline' => $inline ? 1 : 0],
        );
    }

    public function humanSize(): string
    {
        $kb = $this->size_bytes / 1024;
        if ($kb < 1) {
            return $this->size_bytes.' B';
        }

        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.').' MB' : number_format($kb, 0, ',', '.').' KB';
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
