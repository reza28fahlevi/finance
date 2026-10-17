<?php

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    public function definition(): array
    {
        return $this->forMonthAttributes(now());
    }

    /** Periode untuk bulan yang memuat tanggal tertentu. */
    public function forMonth(Carbon|string $date): static
    {
        return $this->state(fn () => $this->forMonthAttributes(Carbon::parse($date)));
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => PeriodStatus::Closed, 'closed_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function forMonthAttributes(Carbon $date): array
    {
        $start = $date->copy()->startOfMonth();

        return [
            'code' => $start->format('Y-m'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::Open,
        ];
    }
}
