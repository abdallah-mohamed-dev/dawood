<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Material;
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
            'category_id' => Category::factory(),
            'name' => $this->faker->unique()->word(),
            'unit' => $this->faker->randomElement(['قطعة', 'متر', 'لوح', 'كجم']),
        ];
    }
}
