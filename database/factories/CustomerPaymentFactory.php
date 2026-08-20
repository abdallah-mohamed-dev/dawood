<?php

namespace Database\Factories;

use App\Models\CustomerPayment;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'paid_at' => now()->toDateString(),
            'note' => null,
        ];
    }
}
