<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the dashboard page still opens for the admin', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk();
});

test('the root path still sends the admin to the dashboard', function () {
    $this->actingAs($this->admin)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('the dashboard entry is gone from the sidebar menu', function () {
    // Checked on another page: the dashboard's own <title> carries the label,
    // so asserting on the dashboard itself would never pass.
    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('لوحة التحكم');
});
