<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomMaterial>
 */
class RoomMaterialFactory extends Factory
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
            'material_id' => Material::factory(),
            'required_quantity' => $this->faker->numberBetween(1_000, 10_000),
            'issued_quantity' => 0,
            'cost' => 0,
        ];
    }
}
