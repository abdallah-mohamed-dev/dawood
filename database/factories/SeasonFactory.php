<?php

namespace Database\Factories;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(100, 9_999),
            'name' => null,
            'started_at' => now()->subMonths(6)->toDateString(),
            'status' => SeasonStatus::Open,
            'loss_carried_in' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SeasonStatus::Closed,
            'ended_at' => now()->toDateString(),
            'closed_at' => now(),
            'loss_carried_out' => 0,
            'distributable_profit' => 0,
            'net_profit' => 0,
        ]);
    }
}
