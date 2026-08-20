<?php

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access the profit report', function () {
    $this->get(route('reports.profit'))->assertRedirect(route('login'));
});

test('the profit report shows revenue, cost, expenses, and net profit for completed rooms', function () {
    Room::factory()->create(['sale_price' => 3_000_000, 'status' => RoomStatus::Completed]);

    $response = $this->actingAs($this->admin)->get(route('reports.profit'));

    $response->assertOk()
        ->assertSee('30,000.00 ج.م');
});
