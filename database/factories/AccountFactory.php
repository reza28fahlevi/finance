<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(AccountType::cases());

        return [
            'code' => $type->codePrefix().'-'.fake()->unique()->numerify('9###'),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => $type,
            'normal_balance' => $type->defaultNormalBalance(),
            'parent_id' => null,
            'is_postable' => true,
            'is_active' => true,
            'is_cash' => false,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'normal_balance' => $type->defaultNormalBalance(),
            'code' => $type->codePrefix().'-'.fake()->unique()->numerify('9###'),
        ]);
    }

    public function cash(): static
    {
        return $this->ofType(AccountType::Asset)->state(fn () => ['is_cash' => true, 'name' => 'Kas '.fake()->company()]);
    }

    public function header(): static
    {
        return $this->state(fn () => ['is_postable' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
