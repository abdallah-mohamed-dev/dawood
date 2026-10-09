<?php

use App\Enums\RoomStatus;
use App\Models\CashboxTransaction;
use App\Models\Material;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\ProfitService;
use App\Services\RoomMaterialService;
use App\Services\RoomService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->room = Room::factory()->create(['status' => RoomStatus::InProgress]);
});

test('guests cannot save a pricing', function () {
    $this->post(route('rooms.pricing.save', $this->room), [])->assertRedirect(route('login'));
});

test('a pricing for an in-progress room is saved in piastres', function () {
    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), [
            'estimated_materials' => '600',
            'estimated_accessories' => '300',
            'estimated_labor' => '',
            'estimated_other' => '50',
            'expected_duration_days' => '8',
        ])
        ->assertSessionHasNoErrors();

    $room = $this->room->fresh();
    expect($room->getRawOriginal('estimated_materials'))->toBe(600);
    expect($room->getRawOriginal('estimated_accessories'))->toBe(300);
    expect($room->getRawOriginal('estimated_labor'))->toBeNull();
    expect($room->getRawOriginal('estimated_other'))->toBe(50);
    expect($room->expected_duration_days)->toBe(8);
});

test('priced_at is set the first time and kept on later edits', function () {
    $this->actingAs($this->admin)->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '100']);
    $first = $this->room->fresh()->priced_at->toDateString();

    $this->room->update(['priced_at' => '2026-01-01']);
    $this->actingAs($this->admin)->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '200']);

    expect($this->room->fresh()->priced_at->toDateString())->toBe('2026-01-01');
    expect($first)->not->toBeEmpty();
});

test('a completed room refuses a pricing in Arabic and keeps the old values', function () {
    $this->room->update(['status' => RoomStatus::Completed, 'estimated_materials' => 100]);

    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '999'])
        ->assertSessionHas('error', 'الغرفة مكتملة، التسعير مقفول.');

    expect($this->room->fresh()->getRawOriginal('estimated_materials'))->toBe(100);
});

test('all estimates empty is a valid pricing', function () {
    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), [])
        ->assertSessionHasNoErrors();

    expect($this->room->fresh()->getRawOriginal('estimated_materials'))->toBeNull();
});

test('a negative estimate is refused in Arabic', function () {
    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '-5'])
        ->assertSessionHasErrors(['estimated_materials' => 'تقدير الخامات غير صالح.']);
});

test('a malformed estimate is reported on its own field only', function () {
    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '100', 'estimated_accessories' => 'abc'])
        ->assertSessionHasErrors(['estimated_accessories' => 'تقدير الاكسسوارات غير صالح.'])
        ->assertSessionDoesntHaveErrors('estimated_materials');
});

test('a duration of zero is refused in Arabic', function () {
    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), ['expected_duration_days' => '0'])
        ->assertSessionHasErrors(['expected_duration_days' => 'مدة التنفيذ لازم تكون يوم واحد على الأقل.']);
});

test('a pricing does not change net profit, revenue or the cashbox', function () {
    $profit = app(ProfitService::class);
    $cashbox = app(CashboxService::class);
    $net = $profit->netProfit();
    $revenue = $profit->revenue();
    $balance = $cashbox->balance();
    $rows = CashboxTransaction::query()->count();

    $this->actingAs($this->admin)
        ->post(route('rooms.pricing.save', $this->room), ['estimated_materials' => '50000', 'estimated_labor' => '9000']);

    expect($profit->netProfit())->toBe($net);
    expect($profit->revenue())->toBe($revenue);
    expect($cashbox->balance())->toBe($balance);
    expect(CashboxTransaction::query()->count())->toBe($rows);
});

test('the room starts its clock the first time it goes in progress', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Draft]);

    app(RoomService::class)->changeStatus($room, RoomStatus::InProgress);

    expect($room->fresh()->started_at->toDateString())->toBe(now()->toDateString());
});

test('coming back from completed keeps the original start and takes the new completion', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $room->update(['started_at' => '2026-01-01']);
    $roomService = app(RoomService::class);

    $roomService->changeStatus($room, RoomStatus::Completed);
    $roomService->changeStatus($room, RoomStatus::InProgress);
    $roomService->changeStatus($room, RoomStatus::Completed);

    expect($room->fresh()->started_at->toDateString())->toBe('2026-01-01');
    expect($room->fresh()->completed_at->toDateString())->toBe(now()->toDateString());
});

test('going straight from draft to completed leaves the start empty', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Draft]);

    app(RoomService::class)->changeStatus($room, RoomStatus::Completed);

    expect($room->fresh()->started_at)->toBeNull();
    expect($room->fresh()->completed_at)->not->toBeNull();
});

test('the activity section lists recent changes to the room', function () {
    $this->room->update(['sale_price' => 1_230]);
    $material = Material::factory()->create();
    app(RoomMaterialService::class)->addRequirement($this->room, $material, 1);

    $this->actingAs($this->admin)
        ->get(route('rooms.show', $this->room))
        ->assertOk()
        ->assertSee('آخر التعديلات على الغرفة')
        ->assertSee($material->name);
});
