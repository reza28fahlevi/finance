<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\JournalTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalTemplate>
 */
class JournalTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_type' => TransactionType::CashOut,
            'debit_account_id' => fn () => Account::factory(),
            'credit_account_id' => fn () => Account::factory(),
        ];
    }
}
