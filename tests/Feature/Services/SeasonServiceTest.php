<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Exceptions\SeasonCloseFailedException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\ProfitService;
use App\Services\SeasonBackupService;
use App\Services\SeasonService;

beforeEach(function () {
    // The real backup copies the on-disk database; tests use an in-memory one.
    app()->instance(SeasonBackupService::class, new class extends SeasonBackupService
    {
        public function create(): string
        {
            return 'fake-backup.sqlite';
        }
    });

    $this->seasons = app(SeasonService::class);
    $this->profit = app(ProfitService::class);
    $this->partners = app(PartnerService::class);

    // The first season starts in January so closes can be dated after it.
    Season::query()->where('status', SeasonStatus::Open)->update(['started_at' => '2026-01-01']);
    $this->partner = Partner::factory()->create(['percentage' => 2_000]); // 20%
});

/** A completed room with sale, material cost, labor and an admin expense. */
function seasonProfit(int $sale, int $materials, int $labor, int $admin): void
{
    $room = Room::factory()->create(['status' => RoomStatus::Completed, 'sale_price' => $sale]);
    RoomMaterial::factory()->create([
        'room_id' => $room->id,
        'material_id' => Material::factory()->create()->id,
        'cost' => $materials,
    ]);
    RoomCost::factory()->create(['room_id' => $room->id, 'type' => RoomCostType::Labor, 'amount' => $labor]);

    if ($admin > 0) {
        Expense::factory()->create([
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'amount' => $admin,
            'occurred_at' => '2026-03-01',
        ]);
    }
}

test('a winning season freezes its profit and pays the partner their share', function () {
    // Revenue 100,000 − materials 40,000 − labor 10,000 − expenses 20,000 = 30,000 EGP.
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);

    $this->seasons->close('2026-06-30');

    $closed = Season::query()->where('number', 1)->first();
    expect($closed->getRawOriginal('net_profit'))->toBe(3_000_000);
    expect($closed->getRawOriginal('distributable_profit'))->toBe(3_000_000);

    $share = SeasonPartnerShare::query()->where('partner_id', $this->partner->id)->first();
    expect($share->getRawOriginal('share_amount'))->toBe(600_000); // 6,000 EGP
    expect($share->getRawOriginal('carried_out'))->toBe(600_000);
});

test('a losing season distributes nothing and carries the whole loss forward', function () {
    // 0 − materials 20,000 ⇒ net −20,000 EGP
    seasonProfit(0, 2_000_000, 0, 0);

    $this->seasons->close('2026-06-30');

    $closed = Season::query()->where('number', 1)->first();
    expect($closed->getRawOriginal('net_profit'))->toBe(-2_000_000);
    expect($closed->getRawOriginal('distributable_profit'))->toBe(0);
    expect($closed->getRawOriginal('loss_carried_out'))->toBe(2_000_000);

    expect(SeasonPartnerShare::query()->where('partner_id', $this->partner->id)->toBase()->value('share_amount'))->toBe(0);
    expect(Season::query()->where('status', SeasonStatus::Open)->toBase()->value('loss_carried_in'))->toBe(2_000_000);
});

test('the next season pays out only after the carried loss is covered', function () {
    seasonProfit(0, 2_000_000, 0, 0); // loss 20,000 EGP
    $this->seasons->close('2026-06-30');

    // Next season: 50,000 EGP profit against the 20,000 EGP carried loss.
    seasonProfit(5_000_000, 0, 0, 0);
    $this->seasons->close('2026-12-31');

    $second = Season::query()->where('number', 2)->first();
    expect($second->getRawOriginal('loss_carried_in'))->toBe(2_000_000);
    expect($second->getRawOriginal('distributable_profit'))->toBe(3_000_000);

    // The most important check: 20% of 30,000 EGP, not 20% of 50,000.
    expect(SeasonPartnerShare::query()->where('season_id', $second->id)->toBase()->value('share_amount'))->toBe(600_000);
});

test('losses stack when they are not covered', function () {
    seasonProfit(0, 2_000_000, 0, 0);
    $this->seasons->close('2026-06-30');

    seasonProfit(0, 1_000_000, 0, 0);
    $this->seasons->close('2026-12-31');

    expect(Season::query()->where('number', 2)->toBase()->value('loss_carried_out'))->toBe(3_000_000);
});

test('a partial cover leaves the rest of the loss carried', function () {
    seasonProfit(0, 2_000_000, 0, 0);
    $this->seasons->close('2026-06-30');

    // 5,000 EGP profit against a 20,000 EGP carried loss.
    seasonProfit(500_000, 0, 0, 0);
    $this->seasons->close('2026-12-31');

    $second = Season::query()->where('number', 2)->first();
    expect($second->getRawOriginal('distributable_profit'))->toBe(0);
    expect($second->getRawOriginal('loss_carried_out'))->toBe(1_500_000);
});

test('completed and cancelled rooms are archived; in-progress and draft rooms carry on', function () {
    $completed = Room::factory()->create(['status' => RoomStatus::Completed]);
    $cancelled = Room::factory()->create(['status' => RoomStatus::Cancelled]);
    $inProgress = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $draft = Room::factory()->create(['status' => RoomStatus::Draft]);

    $this->seasons->close('2026-06-30');

    expect($completed->fresh()->season_id)->not->toBeNull();
    expect($cancelled->fresh()->season_id)->not->toBeNull();
    expect($inProgress->fresh()->season_id)->toBeNull();
    expect($draft->fresh()->season_id)->toBeNull();
});

test('work in progress carries forward at its cost', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    RoomCost::factory()->create(['room_id' => $room->id, 'type' => RoomCostType::Labor, 'amount' => 700_000]);

    $this->seasons->close('2026-06-30');

    expect(Season::query()->where('number', 1)->toBase()->value('wip_carried_forward'))->toBe(700_000);
    expect(RoomCost::query()->where('room_id', $room->id)->value('season_id'))->toBeNull();
});

test('cashbox balance and stock value do not change when a season closes', function () {
    $cashbox = app(CashboxService::class);
    $inventory = app(InventoryService::class);
    $material = Material::factory()->create(['unit_price' => 10_000]);
    $inventory->addStock($material, 5_000, 10_000, '2026-02-01', PaymentMethod::Cash);
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);

    $balance = $cashbox->balance();
    $stock = $inventory->stockValue();

    $this->seasons->close('2026-06-30');

    expect($cashbox->balance())->toBe($balance);
    expect($inventory->stockValue())->toBe($stock);
});

test('a carried share is negative when the partner took more than they were owed', function () {
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000); // share 600,000
    $this->partners->withdraw($this->partner, 700_000, '2026-05-01');

    $this->seasons->close('2026-06-30');

    expect(SeasonPartnerShare::query()->where('partner_id', $this->partner->id)->toBase()->value('carried_out'))->toBe(-100_000);
});

test('the percentage is frozen with the season: a later change does not move the closed numbers', function () {
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);
    $this->seasons->close('2026-06-30');

    $this->partner->update(['percentage' => 3_000]);

    expect(SeasonPartnerShare::query()->where('partner_id', $this->partner->id)->toBase()->value('share_amount'))->toBe(600_000);
    expect(SeasonPartnerShare::query()->where('partner_id', $this->partner->id)->toBase()->value('percentage'))->toBe(2_000);
});

test('only one season is open at a time', function () {
    $this->seasons->close('2026-06-30');
    $this->seasons->close('2026-12-31');

    expect(Season::query()->where('status', SeasonStatus::Open)->count())->toBe(1);
});

test('reopen puts everything back the way it was before the close', function () {
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);
    $this->seasons->close('2026-06-30');

    $this->seasons->reopen(Season::query()->where('number', 1)->first());

    expect(Season::query()->where('number', 1)->toBase()->value('status'))->toBe('open');
    expect(Season::query()->where('number', 2)->exists())->toBeFalse();
    expect(SeasonPartnerShare::query()->count())->toBe(0);
    expect(Expense::query()->whereNotNull('season_id')->count())->toBe(0);
    expect($this->profit->netProfit())->toBe(3_000_000);
});

test('reopen is refused once the new season has data in it', function () {
    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);
    $this->seasons->close('2026-06-30');
    $this->seasons->close('2026-12-31');

    // Closing twice makes season 2 the latest closed; season 1 can no longer be reopened.
    expect(fn () => $this->seasons->reopen(Season::query()->where('number', 1)->first()))
        ->toThrow(RuntimeException::class);
});

test('a close that cannot back up the database does not happen at all', function () {
    app()->instance(SeasonBackupService::class, new class extends SeasonBackupService
    {
        public function create(): string
        {
            throw new SeasonCloseFailedException('فشل الباكب');
        }
    });
    app()->forgetInstance(SeasonService::class);
    $this->seasons = app(SeasonService::class);

    seasonProfit(10_000_000, 4_000_000, 1_000_000, 2_000_000);

    expect(fn () => $this->seasons->close('2026-06-30'))->toThrow(SeasonCloseFailedException::class);

    expect(Season::query()->where('number', 1)->toBase()->value('status'))->toBe('open');
    expect(Expense::query()->whereNotNull('season_id')->count())->toBe(0);
    expect(Room::query()->whereNotNull('season_id')->count())->toBe(0);
});

test('a close date before the season start is refused', function () {
    expect(fn () => $this->seasons->close('2025-12-31'))->toThrow(InvalidArgumentException::class);
});

test('the open season is never closed by the system on its own', function () {
    // Nothing runs on a schedule: a season stays open until someone closes it.
    expect(Season::query()->where('status', SeasonStatus::Closed)->count())->toBe(0);
});
