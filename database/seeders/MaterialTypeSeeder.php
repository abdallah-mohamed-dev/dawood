<?php

namespace Database\Seeders;

use App\Models\MaterialType;
use Illuminate\Database\Seeder;

class MaterialTypeSeeder extends Seeder
{
    public function run(): void
    {
        MaterialType::query()->firstOrCreate(['name' => 'خامة'], ['position' => 1]);
        MaterialType::query()->firstOrCreate(['name' => 'اكسسوار'], ['position' => 2]);
    }
}
