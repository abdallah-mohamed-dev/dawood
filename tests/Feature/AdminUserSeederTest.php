<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('it creates the admin user from configuration', function () {
    config([
        'admin.name' => 'مدير الاختبار',
        'admin.email' => 'admin@test.local',
        'admin.password' => 'super-secret',
    ]);

    (new AdminUserSeeder)->run();

    $admin = User::query()->where('email', 'admin@test.local')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('مدير الاختبار');
    expect(Hash::check('super-secret', $admin->password))->toBeTrue();
});

test('running the seeder twice does not create duplicate admins', function () {
    config([
        'admin.name' => 'مدير الاختبار',
        'admin.email' => 'admin@test.local',
        'admin.password' => 'super-secret',
    ]);

    (new AdminUserSeeder)->run();
    (new AdminUserSeeder)->run();

    expect(User::query()->where('email', 'admin@test.local')->count())->toBe(1);
});

test('re-running the seeder updates the password when it changes', function () {
    config([
        'admin.name' => 'مدير الاختبار',
        'admin.email' => 'admin@test.local',
        'admin.password' => 'first-password',
    ]);
    (new AdminUserSeeder)->run();

    config(['admin.password' => 'second-password']);
    (new AdminUserSeeder)->run();

    $admin = User::query()->where('email', 'admin@test.local')->first();

    expect(Hash::check('second-password', $admin->password))->toBeTrue();
});
