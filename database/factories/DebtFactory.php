<?php

namespace Database\Factories;

use App\Models\Debt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $incurred = $this->faker->dateTimeBetween('-3 months', 'now');

        return [
            'creditor' => $this->faker->company(),
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'incurred_at' => $incurred->format('Y-m-d'),
            'due_at' => null,
            'is_paid' => false,
            'paid_at' => null,
            'note' => null,
        ];
    }
}
