<?php

namespace App\Models;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'ref_no', 'entry_date', 'period_id', 'transaction_id', 'source', 'status',
    'description', 'reversal_of_id', 'created_by', 'posted_at', 'posted_by',
])]
class JournalEntry extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Jurnal POSTED immutable: hanya transisi status ke REVERSED yang diizinkan.
        static::updating(function (JournalEntry $journal) {
            if ($journal->getOriginal('status') === JournalStatus::Posted) {
                $allowed = ['status', 'updated_at'];
                if (array_diff(array_keys($journal->getDirty()), $allowed) !== []
                    || $journal->status !== JournalStatus::Reversed) {
                    throw new LogicException('Jurnal yang sudah POSTED tidak dapat diubah. Gunakan jurnal pembalik.');
                }
            }
            if ($journal->getOriginal('status') === JournalStatus::Reversed) {
                throw new LogicException('Jurnal yang sudah REVERSED tidak dapat diubah.');
            }
        });

        static::deleting(function (JournalEntry $journal) {
            if ($journal->status !== JournalStatus::Draft) {
                throw new LogicException('Hanya jurnal DRAFT yang dapat dihapus.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'source' => JournalSource::class,
            'status' => JournalStatus::class,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_id')->orderBy('line_no');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reversal_of_id');
    }

    public function totalDebit(): string
    {
        return $this->sumColumn('debit');
    }

    public function totalCredit(): string
    {
        return $this->sumColumn('credit');
    }

    public function isBalanced(): bool
    {
        return bccomp($this->totalDebit(), $this->totalCredit(), 2) === 0
            && bccomp($this->totalDebit(), '0', 2) === 1;
    }

    private function sumColumn(string $column): string
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return $lines->reduce(fn (string $sum, JournalLine $line) => bcadd($sum, (string) $line->{$column}, 2), '0.00');
    }
}
