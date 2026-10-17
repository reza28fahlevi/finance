<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_type', 'debit_account_id', 'credit_account_id', 'description_template'])]
class JournalTemplate extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['transaction_type' => TransactionType::class];
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public static function forType(TransactionType $type): ?self
    {
        return static::where('transaction_type', $type)->first();
    }
}
