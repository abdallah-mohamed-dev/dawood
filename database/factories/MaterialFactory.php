<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'unit' => $this->faker->randomElement(['قطعة', 'متر', 'لوح', 'كجم']),
            'material_type_id' => MaterialType::query()->firstOrCreate(['name' => 'خامة'], ['position' => 1])->id,
        ];
    }
}
