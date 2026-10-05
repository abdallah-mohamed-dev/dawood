<?php

namespace Database\Factories;

use App\Models\CapitalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapitalItem>
 */
class CapitalItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['معدات', 'إيجار', 'تأسيس']),
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'occurred_at' => now()->toDateString(),
            'note' => null,
        ];
    }
}
