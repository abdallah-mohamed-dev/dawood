<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            MaterialTypeSeeder::class,
        ]);

        // Local development only — never on a real database.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
