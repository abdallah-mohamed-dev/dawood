<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
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
        $batch = InventoryBatch::factory()->create();

        return [
            'material_id' => $batch->material_id,
            'batch_id' => $batch->id,
            'type' => InventoryMovementType::In,
            'quantity' => $batch->getRawOriginal('quantity'),
            'cost' => $this->faker->numberBetween(1_000, 50_000),
            'related_type' => null,
            'related_id' => null,
            'occurred_at' => now()->toDateString(),
        ];
    }
}
