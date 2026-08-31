<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'المدير',
        'email' => 'admin@dawood.test',
        'password' => Hash::make('old-password'),
    ]);
});

test('guests cannot access the profile page', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('the profile page shows the current name and email', function () {
    $response = $this->actingAs($this->admin)->get(route('profile.edit'));

    $response->assertOk()
        ->assertSee('الملف الشخصي')
        ->assertSee('المدير')
        ->assertSee('admin@dawood.test');
});

test('the admin can update their name and email', function () {
    $this->actingAs($this->admin)->put(route('profile.update'), [
        'name' => 'اسم جديد',
        'email' => 'new@dawood.test',
    ])->assertRedirect(route('profile.edit'));

    $this->admin->refresh();
    expect($this->admin->name)->toBe('اسم جديد');
    expect($this->admin->email)->toBe('new@dawood.test');
});

test('updating the profile with an email used by another user fails validation', function () {
    User::factory()->create(['email' => 'taken@dawood.test']);

    $response = $this->actingAs($this->admin)->put(route('profile.update'), [
        'name' => 'المدير',
        'email' => 'taken@dawood.test',
    ]);

    $response->assertSessionHasErrors('email');
    expect($this->admin->fresh()->email)->toBe('admin@dawood.test');
});

test('keeping the same email does not fail uniqueness validation', function () {
    $response = $this->actingAs($this->admin)->put(route('profile.update'), [
        'name' => 'اسم محدث',
        'email' => 'admin@dawood.test',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($this->admin->fresh()->name)->toBe('اسم محدث');
});

test('updating the profile without a name fails validation', function () {
    $response = $this->actingAs($this->admin)->put(route('profile.update'), [
        'name' => '',
        'email' => 'admin@dawood.test',
    ]);

    $response->assertSessionHasErrors('name');
    expect($this->admin->fresh()->name)->toBe('المدير');
});

test('the admin can change their password with the correct current password', function () {
    $this->actingAs($this->admin)->put(route('profile.password.update'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('profile.edit'));

    expect(Hash::check('brand-new-password', $this->admin->fresh()->password))->toBeTrue();
});

test('changing the password with a wrong current password is rejected', function () {
    $response = $this->actingAs($this->admin)->put(route('profile.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertSessionHasErrors('current_password');
    expect(Hash::check('old-password', $this->admin->fresh()->password))->toBeTrue();
});

test('changing the password with a mismatched confirmation is rejected', function () {
    $response = $this->actingAs($this->admin)->put(route('profile.password.update'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');
    expect(Hash::check('old-password', $this->admin->fresh()->password))->toBeTrue();
});

test('a new password shorter than eight characters is rejected', function () {
    $response = $this->actingAs($this->admin)->put(route('profile.password.update'), [
        'current_password' => 'old-password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
    expect(Hash::check('old-password', $this->admin->fresh()->password))->toBeTrue();
});

test('the admin stays logged in after changing their password', function () {
    $this->actingAs($this->admin)->put(route('profile.password.update'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $this->assertAuthenticatedAs($this->admin->fresh());
});
