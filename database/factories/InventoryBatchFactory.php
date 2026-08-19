<?php

namespace Database\Factories;

use App\Models\InventoryBatch;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1_000, 20_000);

        return [
            'material_id' => Material::factory(),
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $this->faker->numberBetween(1_000, 50_000),
            'purchase_date' => now()->toDateString(),
        ];
    }

    public function depleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_quantity' => 0,
        ]);
    }
}
