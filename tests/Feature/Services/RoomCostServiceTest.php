<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\RoomCostType;
use App\Models\CashboxTransaction;
use App\Models\Room;
use App\Models\RoomCost;
use App\Services\CashboxService;
use App\Services\RoomCostService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->costs = new RoomCostService($this->cashbox);
    $this->room = Room::factory()->create();
});

test('adding a labour payment records a cashbox outflow with the labour kind', function () {
    $cost = $this->costs->create($this->room, RoomCostType::Labor, 500_000, '2026-01-05', 'دفعة أولى');

    expect($cost->getRawOriginal('amount'))->toBe(500_000);
    expect($cost->type)->toBe(RoomCostType::Labor);

    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->type)->toBe(CashboxTransactionType::Out);
    expect($transaction->kind)->toBe(CashboxTransactionKind::RoomLabor);
    expect($transaction->getRawOriginal('amount'))->toBe(500_000);
    expect($transaction->source_id)->toBe($cost->id);
    expect($transaction->source_type)->toBe(RoomCost::class);
});

test('adding an extra expense records a cashbox outflow with the room-expense kind', function () {
    $this->costs->create($this->room, RoomCostType::Other, 120_000, '2026-01-05', 'نقل');

    expect(CashboxTransaction::query()->sole()->kind)->toBe(CashboxTransactionKind::RoomExpense);
});

test('room costs reduce the cashbox balance', function () {
    $this->cashbox->setOpeningBalance(1_000_000, '2026-01-01');
    $this->costs->create($this->room, RoomCostType::Labor, 300_000, '2026-01-05');
    $this->costs->create($this->room, RoomCostType::Other, 100_000, '2026-01-06');

    expect($this->cashbox->balance())->toBe(600_000);
});

test('deleting a room cost removes its cashbox transaction', function () {
    $this->cashbox->setOpeningBalance(1_000_000, '2026-01-01');
    $cost = $this->costs->create($this->room, RoomCostType::Labor, 300_000, '2026-01-05');

    $this->costs->delete($cost);

    expect(RoomCost::query()->count())->toBe(0);
    expect($this->cashbox->balance())->toBe(1_000_000);
    expect(CashboxTransaction::query()->where('source_type', RoomCost::class)->count())->toBe(0);
});

test('a zero or negative amount is rejected', function (int $amount) {
    expect(fn () => $this->costs->create($this->room, RoomCostType::Labor, $amount, '2026-01-05'))
        ->toThrow(InvalidArgumentException::class);

    expect(RoomCost::query()->count())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
})->with([0, -1, -500_000]);

test('the room exposes its labour, other and total costs separately', function () {
    $this->costs->create($this->room, RoomCostType::Labor, 300_000, '2026-01-05');
    $this->costs->create($this->room, RoomCostType::Labor, 200_000, '2026-01-06');
    $this->costs->create($this->room, RoomCostType::Other, 50_000, '2026-01-07');

    $room = Room::query()->whereKey($this->room->id)->sole();

    expect($room->laborCost())->toBe(500_000);
    expect($room->otherCost())->toBe(50_000);
    expect($room->costsTotal())->toBe(550_000);
    expect($room->hasCosts())->toBeTrue();
});

test('the same totals come out whether the relation is loaded or queried', function () {
    $this->costs->create($this->room, RoomCostType::Labor, 300_000, '2026-01-05');
    $this->costs->create($this->room, RoomCostType::Other, 50_000, '2026-01-07');

    $queried = Room::query()->whereKey($this->room->id)->sole();
    $loaded = Room::query()->whereKey($this->room->id)->with('roomCosts')->sole();

    expect($loaded->laborCost())->toBe($queried->laborCost());
    expect($loaded->otherCost())->toBe($queried->otherCost());
    expect($loaded->costsTotal())->toBe($queried->costsTotal());
    expect($loaded->hasCosts())->toBe($queried->hasCosts());
});
