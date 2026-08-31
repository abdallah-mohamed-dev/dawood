<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the single administrator account when the system has no user yet.
     *
     * Once an account exists, its name, email and password belong to the admin
     * and are edited from the profile page — the seeder must never write over
     * them from the environment, and must never add a second admin because the
     * email was changed in the UI.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        $admin = User::query()->create([
            'name' => config('admin.name'),
            'email' => config('admin.email'),
            'password' => config('admin.password'),
        ]);

        $admin->forceFill(['email_verified_at' => now()])->save();
    }
}
