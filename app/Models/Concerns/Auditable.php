<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Catat perubahan data master (create/update/delete) ke audit_logs.
 * Perubahan status transaksi/jurnal dicatat eksplisit oleh service masing-masing.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->log('created', $model, null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditLogger::class)->log('updated', $model, $model->auditableAttributes($old), $changes);
        });

        static::deleted(function (Model $model) {
            app(AuditLogger::class)->log('deleted', $model, $model->auditableAttributes($model->getAttributes()), null);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function auditableAttributes(array $attributes): array
    {
        $exclude = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return collect($attributes)
            ->except(array_merge($exclude, ['created_at', 'updated_at']))
            ->map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value)
            ->all();
    }
}
