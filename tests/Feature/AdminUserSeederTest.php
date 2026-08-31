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
    expect($admin->email_verified_at)->not->toBeNull();
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

test('the seeder does not overwrite profile changes made from the app', function () {
    config([
        'admin.name' => 'مدير الاختبار',
        'admin.email' => 'admin@test.local',
        'admin.password' => 'first-password',
    ]);
    (new AdminUserSeeder)->run();

    $admin = User::query()->where('email', 'admin@test.local')->firstOrFail();
    $admin->update([
        'name' => 'اسم من الواجهة',
        'password' => 'password-from-ui',
    ]);

    config(['admin.name' => 'اسم من البيئة', 'admin.password' => 'password-from-env']);
    (new AdminUserSeeder)->run();

    $admin->refresh();
    expect($admin->name)->toBe('اسم من الواجهة');
    expect(Hash::check('password-from-ui', $admin->password))->toBeTrue();
});

test('the seeder creates no second admin after the email was changed from the app', function () {
    config([
        'admin.name' => 'مدير الاختبار',
        'admin.email' => 'admin@test.local',
        'admin.password' => 'first-password',
    ]);
    (new AdminUserSeeder)->run();

    User::query()->where('email', 'admin@test.local')->firstOrFail()
        ->update(['email' => 'changed@test.local']);

    (new AdminUserSeeder)->run();

    expect(User::query()->count())->toBe(1);
    expect(User::query()->first()->email)->toBe('changed@test.local');
});
