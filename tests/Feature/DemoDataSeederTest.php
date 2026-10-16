<?php

use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Models\Room;
use App\Models\Season;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\ProfitService;
use App\Services\SeasonBackupService;
use App\Services\SeasonService;
use App\Services\ShortageService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MaterialTypeSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The demo data is what the app is looked at with locally, so it has to stay
 * a believable, profitable story: a closed first season and an open second
 * one. Built through the real services, so these also catch a service change
 * that would make the demo throw.
 */
beforeEach(function () {
    // Closing a season copies the real database file — not wanted from a test.
    $this->mock(SeasonBackupService::class)->shouldReceive('create')->andReturn('demo');

    $this->seed([MaterialTypeSeeder::class, DemoDataSeeder::class]);
});

test('season 1 is closed at the end of july with a profit, season 2 is open', function () {
    $first = Season::query()->where('number', 1)->sole();
    $second = Season::query()->where('number', 2)->sole();

    expect($first->status)->toBe(SeasonStatus::Closed);
    expect($first->started_at->toDateString())->toBe('2026-05-01');
    expect($first->ended_at->toDateString())->toBe('2026-07-31');
    expect((int) $first->getRawOriginal('net_profit'))->toBeGreaterThan(0);
    expect((int) $first->getRawOriginal('loss_carried_out'))->toBe(0);

    expect($second->status)->toBe(SeasonStatus::Open);
    expect($second->started_at->toDateString())->toBe('2026-08-01');
});

test('nothing is dated after the story ends, so season 2 can be closed and reopened', function () {
    $end = DemoDataSeeder::STORY_ENDS.' 23:59:59';

    foreach (['rooms', 'expenses', 'room_costs', 'partner_withdrawals', 'customer_payments', 'cashbox_transactions', 'inventory_movements', 'room_materials'] as $table) {
        expect((string) DB::table($table)->max('created_at'))->toBeLessThanOrEqual($end);
    }

    // The exact failure seen by hand: a future-dated row made the closed
    // season look touched, and the reopen button never appeared.
    $this->travelTo(CarbonImmutable::parse(DemoDataSeeder::STORY_ENDS)->addDay()->setTime(8, 0));
    $closed = app(SeasonService::class)->close(DemoDataSeeder::STORY_ENDS);

    expect(app(SeasonService::class)->canReopen($closed))->toBeTrue();
});

test('the rooms cover every state, and season 1 sealed only its finished ones', function () {
    expect(Room::query()->where('status', RoomStatus::Completed)->count())->toBe(12);
    expect(Room::query()->where('status', RoomStatus::InProgress)->count())->toBe(2);
    expect(Room::query()->where('status', RoomStatus::Draft)->count())->toBe(1);
    expect(Room::query()->where('status', RoomStatus::Cancelled)->count())->toBe(1);

    // 7 completed + 1 cancelled; the bedroom in progress at the close carried forward.
    $first = Season::query()->where('number', 1)->value('id');
    expect(Room::query()->where('season_id', $first)->count())->toBe(8);
});

test('the open season is profitable and the cashbox is positive', function () {
    expect(app(ProfitService::class)->netProfit())->toBeGreaterThan(0);
    expect(app(CashboxService::class)->balance())->toBeGreaterThan(0);
});

test('the priced draft room shows up as short on wood', function () {
    $shortages = app(ShortageService::class)->forActiveRooms();

    expect($shortages)->not->toBeEmpty();
});

test('every page opens on top of the demo data', function () {
    $user = User::factory()->create();

    foreach (['dashboard', 'customers.index', 'inventory.materials.index', 'rooms.index', 'inventory.shortages.index', 'inventory.movements.index', 'payments.index', 'expenses.index', 'debts.index', 'cashbox.index', 'reports.profit', 'reports.labor', 'seasons.index', 'partners.index', 'capital.index', 'settings.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }

    foreach (Room::query()->pluck('id') as $id) {
        $this->actingAs($user)->get(route('rooms.show', $id))->assertOk();
    }
});
