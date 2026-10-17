<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number', 'type', 'trx_date', 'due_date', 'description', 'amount',
    'debit_account_id', 'credit_account_id', 'party_name', 'reference',
    'status', 'period_id', 'created_by', 'submitted_at', 'rejection_reason',
])]
class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'trx_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class)->latest('acted_at');
    }

    public function journal(): HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
