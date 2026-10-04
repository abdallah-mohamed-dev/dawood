<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests are redirected to login from the settings page', function () {
    $this->get(route('settings.index'))->assertRedirect(route('login'));
});

test('the admin can open the settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('الإعدادات');
});
