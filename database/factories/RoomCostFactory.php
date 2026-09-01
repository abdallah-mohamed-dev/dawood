<?php

namespace Database\Factories;

use App\Enums\RoomCostType;
use App\Models\Room;
use App\Models\RoomCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomCost>
 */
class RoomCostFactory extends Factory
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
            'type' => RoomCostType::Labor,
            'description' => null,
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'occurred_at' => now()->toDateString(),
        ];
    }

    public function other(): static
    {
        return $this->state(fn () => ['type' => RoomCostType::Other]);
    }
}
