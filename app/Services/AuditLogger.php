<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $note = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'note' => $note,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    /** Catat perubahan status (transaksi/jurnal/periode). */
    public function statusChange(Model $subject, string $action, ?string $from, string $to, ?string $note = null): AuditLog
    {
        return $this->log($action, $subject, $from ? ['status' => $from] : null, ['status' => $to], $note);
    }
}
