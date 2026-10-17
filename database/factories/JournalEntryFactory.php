<?php

namespace Database\Factories;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Header jurnal saja; baris ditambahkan lewat ->lines()->createMany() atau gunakan JournalService.
 *
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ref_no' => 'JV-'.fake()->unique()->numerify('#######'),
            'entry_date' => now()->toDateString(),
            'period_id' => fn () => AccountingPeriod::forDate(now()) ?? AccountingPeriod::factory(),
            'source' => JournalSource::Manual,
            'status' => JournalStatus::Draft,
            'description' => fake()->sentence(),
            'created_by' => fn () => User::factory()->accountant(),
        ];
    }
}
