<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'room_type' => $this->faker->randomElement(['غرفة نوم', 'مطبخ', 'صالون', 'غرفة سفرة']),
            'sale_price' => $this->faker->numberBetween(500_000, 5_000_000),
            'status' => RoomStatus::Draft,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatus::Completed]);
    }
}
