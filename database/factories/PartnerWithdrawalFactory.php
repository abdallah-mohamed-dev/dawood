<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerWithdrawal>
 */
class PartnerWithdrawalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'occurred_at' => now()->toDateString(),
            'note' => null,
        ];
    }
}
