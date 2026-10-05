<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $material = Material::factory()->create(['quantity' => 10_000]);

        return [
            'material_id' => $material->id,
            'type' => InventoryMovementType::In,
            'quantity' => $material->getRawOriginal('quantity'),
            'cost' => $this->faker->numberBetween(1_000, 50_000),
            'related_type' => null,
            'related_id' => null,
            'occurred_at' => now()->toDateString(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn () => ['type' => InventoryMovementType::Out]);
    }
}
