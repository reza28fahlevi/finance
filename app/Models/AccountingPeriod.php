<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'start_date', 'end_date', 'status', 'closed_at', 'closed_by'])]
class AccountingPeriod extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'status' => PeriodStatus::class,
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'period_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'period_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PeriodStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open;
    }

    /** Periode yang memuat tanggal tertentu. */
    public static function forDate(CarbonInterface|string $date): ?self
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /** Periode terbuka paling akhir (dipakai sebagai "periode aktif" di UI). */
    public static function current(): ?self
    {
        $today = static::forDate(now());
        if ($today?->isOpen()) {
            return $today;
        }

        return static::open()->orderByDesc('start_date')->first()
            ?? $today
            ?? static::orderByDesc('start_date')->first();
    }

    public function label(): string
    {
        return $this->start_date->translatedFormat('F Y');
    }
}
