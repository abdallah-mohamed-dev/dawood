<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests visiting the root are redirected to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('authenticated users visiting the root are redirected to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});

test('guests cannot access the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('an authenticated user visiting the login page is redirected to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
});

test('the login page renders for guests in Arabic RTL', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('تسجيل الدخول')
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ar"', false);
});

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('a user cannot log in with incorrect credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('the login form requires an email and a password', function () {
    $response = $this->post(route('login.store'), []);

    $response->assertSessionHasErrors(['email', 'password']);
});

test('a logged in user can log out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a logged out session cannot revisit a protected page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('there is no registration route', function () {
    $this->get('/register')->assertNotFound();
});

test('login attempts are throttled after too many failures', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
