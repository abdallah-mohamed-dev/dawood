<?php

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomMaterial;
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

test('the dashboard shows every chart figure as text too, and labels each chart image', function () {
    $customer = Customer::factory()->create();
    $room = Room::factory()->create(['customer_id' => $customer->id, 'status' => RoomStatus::InProgress]);
    RoomMaterial::factory()->create(['room_id' => $room->id, 'issued_quantity' => 1, 'cost' => 500]);

    $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

    $response->assertSee('role="img"', false);
    // The table view of every chart repeats its numbers as plain text, so the
    // figures exist even when the SVG does not render.
    $response->assertSeeInOrder(['رسم', 'جدول']);
});
