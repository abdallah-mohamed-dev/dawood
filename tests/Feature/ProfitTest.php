<?php

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use App\Services\ProfitService;

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
        ->assertSeeText('30,000.00 ج.م');
});

test('the profit report shows the waterfall chart and its figures as text', function () {
    Room::factory()->create(['sale_price' => 5_000_000, 'status' => RoomStatus::Completed]);

    $response = $this->actingAs($this->admin)->get(route('reports.profit'))->assertOk();

    $response->assertSee('role="img"', false);
    $response->assertSeeInOrder(['رسم', 'جدول']);
});

test('the waterfall totals are internally consistent with net profit', function () {
    Room::factory()->create(['sale_price' => 5_000_000, 'status' => RoomStatus::Completed]);

    $this->actingAs($this->admin)->get(route('reports.profit'))->assertOk();

    $profit = app(ProfitService::class);

    expect(
        $profit->revenue() - $profit->costOfMaterials() - $profit->roomCosts()
            - $profit->cancelledRoomCosts() - $profit->adminExpenses()
    )->toBe($profit->netProfit());
});
