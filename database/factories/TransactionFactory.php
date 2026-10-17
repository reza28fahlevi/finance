<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Catatan: untuk alur bisnis lengkap (nomor sequence, audit log), gunakan TransactionService.
 * Factory ini untuk menyiapkan data uji langsung pada status tertentu.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'TRX-'.fake()->unique()->numerify('######'),
            'type' => TransactionType::CashOut,
            'trx_date' => now()->toDateString(),
            'description' => fake()->sentence(),
            'amount' => fake()->numberBetween(1, 900) * 1000,
            'debit_account_id' => fn () => Account::factory()->ofType(AccountType::Expense),
            'credit_account_id' => fn () => Account::factory()->cash(),
            'party_name' => fake()->company(),
            'status' => TransactionStatus::Draft,
            'period_id' => fn () => AccountingPeriod::forDate(now()) ?? AccountingPeriod::factory(),
            'created_by' => fn () => User::factory()->staff(),
        ];
    }

    public function status(TransactionStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === TransactionStatus::Draft ? null : now(),
        ]);
    }

    public function pending(): static
    {
        return $this->status(TransactionStatus::PendingReview);
    }
}
