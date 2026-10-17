<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'normal_balance', 'parent_id', 'is_postable', 'is_active', 'is_cash', 'description'])]
class Account extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'is_cash' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }

    /** Akun aktif yang boleh dijurnal. */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_postable', true);
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->is_postable;
    }

    /** Akun yang sudah dipakai (jurnal/transaksi/template) hanya boleh dinonaktifkan, tidak dihapus. */
    public function isUsed(): bool
    {
        return $this->journalLines()->exists()
            || Transaction::where('debit_account_id', $this->id)->orWhere('credit_account_id', $this->id)->exists()
            || JournalTemplate::where('debit_account_id', $this->id)->orWhere('credit_account_id', $this->id)->exists()
            || $this->children()->exists();
    }

    public function depth(): int
    {
        $depth = 0;
        $parent = $this->parent;
        while ($parent && $depth < 10) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function label(): string
    {
        return "{$this->code} | {$this->name}";
    }
}
