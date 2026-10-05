<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\InventoryMovementType;
use App\Enums\RoomStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\CashboxTransaction;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomMaterialService;

// InventoryService::issue() needs a polymorphic "related" model to attach the
// resulting `out` movements to. RoomMaterial isn't needed to prove that here —
// User stands in as "any model", matching the same approach used for
// CashboxService's polymorphic "source".
beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->material = Material::factory()->create();
});

test('addStock sets the quantity and price, logs an "in" movement, and pays the cashbox', function () {
    $movement = $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(5_000);
    expect($this->material->getRawOriginal('unit_price'))->toBe(55_000); // 550 EGP

    expect($movement->type)->toBe(InventoryMovementType::In);
    expect($movement->getRawOriginal('quantity'))->toBe(5_000);
    expect($movement->getRawOriginal('cost'))->toBe(275_000); // 2,750 EGP

    expect($this->cashbox->balance())->toBe(-275_000);
    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->kind)->toBe(CashboxTransactionKind::InventoryPurchase);
    expect($transaction->getRawOriginal('amount'))->toBe(275_000);
    // The movement is the source of truth the cashbox row points at.
    expect($transaction->source_type)->toBe(InventoryMovement::class);
    expect($transaction->source_id)->toBe($movement->id);
});

test('adding stock at a new price re-prices the whole material, not just the new quantity', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $this->inventory->addStock($this->material, 2_000, 60_000, '2026-01-02');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(7_000);
    expect($this->material->getRawOriginal('unit_price'))->toBe(60_000);
    expect($this->inventory->stockValue())->toBe(420_000); // 7 @ 600 EGP
});

test('reduceStock sells at the current price, logs a "sold" movement, and brings the money back in', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    $movement = $this->inventory->reduceStock($this->material, 2_000, '2026-01-02');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(3_000);

    expect($movement->type)->toBe(InventoryMovementType::Sold);
    expect($movement->getRawOriginal('quantity'))->toBe(2_000);
    expect($movement->getRawOriginal('cost'))->toBe(110_000); // 2 @ 550 EGP

    expect($this->cashbox->balance())->toBe(-275_000 + 110_000);
    $transaction = CashboxTransaction::query()->latest('id')->first();
    expect($transaction->kind)->toBe(CashboxTransactionKind::MaterialSale);
    expect($transaction->getRawOriginal('amount'))->toBe(110_000);
});

test('the reference numbers: add 5000 @ 550, sell 2000, then re-price to 600', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $this->inventory->reduceStock($this->material, 2_000, '2026-01-02');

    expect($this->inventory->currentStock($this->material))->toBe(3_000);
    expect($this->cashbox->balance())->toBe(-165_000);

    $this->inventory->changePrice($this->material, 60_000);

    expect($this->inventory->stockValue())->toBe(180_000); // 3 @ 600 EGP
    expect($this->cashbox->balance())->toBe(-165_000); // a re-price moves no money
});

test('changePrice re-values the stock without touching the cashbox or the quantity', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    expect($this->inventory->stockValue())->toBe(275_000);

    $this->inventory->changePrice($this->material, 60_000);

    $this->material->refresh();
    expect($this->material->getRawOriginal('unit_price'))->toBe(60_000);
    expect($this->material->getRawOriginal('quantity'))->toBe(5_000);
    expect($this->inventory->stockValue())->toBe(300_000); // 5 @ 600 EGP
    expect(CashboxTransaction::query()->count())->toBe(1);
    expect($this->cashbox->balance())->toBe(-275_000);
});

test('issue prices at the current unit price, leaves stock and the cashbox otherwise', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $related = User::factory()->create();

    $result = $this->inventory->issue($this->material, 1_000, $related, '2026-01-02');

    expect($result)->toBe(['cost' => 55_000]); // 1 @ 550 EGP
    expect($this->inventory->currentStock($this->material))->toBe(4_000);
    expect($this->cashbox->balance())->toBe(-275_000);

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::Out)->sole();
    expect($movement->getRawOriginal('cost'))->toBe(55_000);
    expect($movement->related_type)->toBe(User::class);
    expect($movement->related_id)->toBe($related->id);
});

test('returning what was issued gives back the quantity at the cost it was issued at, not the new price', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $related = User::factory()->create();
    $this->inventory->issue($this->material, 1_000, $related, '2026-01-02');

    $this->inventory->changePrice($this->material, 60_000);
    $this->inventory->returnIssued($related);

    expect($this->inventory->currentStock($this->material))->toBe(5_000);
    expect($this->cashbox->balance())->toBe(-275_000);

    $returned = InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->sole();
    expect($returned->getRawOriginal('quantity'))->toBe(1_000);
    expect($returned->getRawOriginal('cost'))->toBe(55_000);
    expect($returned->related_type)->toBe(User::class);
    expect($returned->related_id)->toBe($related->id);

    // The original `out` is still there for audit, next to the `return`.
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Out)->count())->toBe(1);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->count())->toBe(1);
});

test('returning never touches the cashbox or the movement log of a re-priced material value', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $related = User::factory()->create();
    $this->inventory->issue($this->material, 2_000, $related, '2026-01-02');
    $this->inventory->changePrice($this->material, 60_000);

    $transactionsBefore = CashboxTransaction::query()->count();
    $this->inventory->returnIssued($related);

    expect(CashboxTransaction::query()->count())->toBe($transactionsBefore);
    // Stock is back at 5 units, but they are now worth 600 each.
    expect($this->inventory->stockValue())->toBe(300_000);
});

test('stockValueByType splits the warehouse by type and files type-less materials under "none"', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01'); // 275,000

    $other = Material::factory()->create(['material_type_id' => null]);
    $this->inventory->addStock($other, 2_000, 10_000, '2026-01-01'); // 20,000

    $result = $this->inventory->stockValueByType();

    expect($result['by_type'])->toBe([
        $this->material->material_type_id => ['label' => 'خامة', 'value' => 275_000],
        'none' => ['label' => '—', 'value' => 20_000],
    ]);
    expect($result['total'])->toBe(295_000);
});

test('stockValueByType only lists types that actually hold stock', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    $result = $this->inventory->stockValueByType();

    expect(array_keys($result['by_type']))->toBe([$this->material->material_type_id]);
    expect($result['total'])->toBe(275_000);
});

test('stockValueByType labels a type by its name', function () {
    $type = MaterialType::query()->firstOrCreate(['name' => 'خشب'], ['position' => 9]);
    $material = Material::factory()->create(['material_type_id' => $type->id]);
    $this->inventory->addStock($material, 1_000, 10_000, '2026-01-01');

    $result = $this->inventory->stockValueByType();

    expect($result['by_type'][$type->id]['label'])->toBe('خشب');
    expect($result['total'])->toBe(10_000);
});

test('stockValue ignores materials that have run out of stock', function () {
    $this->inventory->addStock($this->material, 1_000, 10_000, '2026-01-01');
    $this->inventory->reduceStock($this->material, 1_000, '2026-01-02');

    expect($this->inventory->stockValue())->toBe(0);
});

test('stockByMaterialIds reads every material in one query and returns raw scaled integers', function () {
    $other = Material::factory()->create();
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $this->inventory->addStock($other, 2_500, 4_000, '2026-01-01');

    $stock = $this->inventory->stockByMaterialIds();

    expect($stock->all())->toBe([$this->material->id => 5_000, $other->id => 2_500]);
    expect($stock->get($this->material->id))->toBe(5_000);

    $filtered = $this->inventory->stockByMaterialIds([$other->id]);
    expect($filtered->all())->toBe([$other->id => 2_500]);
});

test('addStock rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 0, 10_000, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('addStock rejects a zero or negative unit price', function () {
    $this->inventory->addStock($this->material, 1_000, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('addStock rounds a cost that lands exactly on half a piastre up, never down to zero', function () {
    // 0.001 units at 5 EGP → 1×500/1000 = exactly 0.5 piastre → rounds up to 1.
    $movement = $this->inventory->addStock($this->material, 1, 500, '2026-01-01');

    expect($movement->getRawOriginal('cost'))->toBe(1);
    expect($this->cashbox->balance())->toBe(-1);
});

test('addStock rejects a quantity/price pair whose cost rounds down to zero, before writing anything', function () {
    // 0.001 units (scaled 1) at 0.40 EGP/unit (scaled 40) → 1×40/1000 rounds to 0.
    expect(fn () => $this->inventory->addStock($this->material, 1, 40, '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);

    expect(InventoryMovement::query()->count())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
    expect($this->material->fresh()->getRawOriginal('quantity'))->toBe(0);
});

test('changePrice rejects a zero or negative price and leaves the material alone', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    expect(fn () => $this->inventory->changePrice($this->material, 0))
        ->toThrow(InvalidArgumentException::class);

    expect($this->material->fresh()->getRawOriginal('unit_price'))->toBe(55_000);
});

test('reduceStock rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 1_000, 10_000, '2026-01-01');

    $this->inventory->reduceStock($this->material, 0, '2026-01-02');
})->throws(InvalidArgumentException::class);

test('reduceStock refuses to sell more than is on hand and writes nothing', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    expect(fn () => $this->inventory->reduceStock($this->material, 5_001, '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(5_000);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Sold)->count())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(1);
});

test('issue refuses to issue more than is on hand and writes nothing', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    expect(fn () => $this->inventory->issue($this->material, 5_001, User::factory()->create(), '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(5_000);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Out)->count())->toBe(0);
});

test('issue rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    $this->inventory->issue($this->material, 0, User::factory()->create(), '2026-01-02');
})->throws(InvalidArgumentException::class);

test('selling stock is money coming in, not revenue: the profit report does not move', function () {
    $profit = new ProfitService($this->inventory);
    $room = Room::factory()->create(['sale_price' => 300_000, 'status' => RoomStatus::Completed]);
    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');

    $revenueBefore = $profit->revenue();
    $netProfitBefore = $profit->netProfit();

    $this->inventory->reduceStock($this->material, 2_000, '2026-01-02');

    expect($profit->revenue())->toBe($revenueBefore);
    expect($profit->netProfit())->toBe($netProfitBefore);
});

test('changePrice never rewrites what a room already paid for the same material', function () {
    $roomMaterials = new RoomMaterialService($this->inventory);
    $room = Room::factory()->create();

    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01');
    $roomMaterial = $roomMaterials->addRequirement($room, $this->material, 5_000);
    $roomMaterials->issue($roomMaterial, 2_000, '2026-01-02');

    $costBefore = $roomMaterial->fresh()->getRawOriginal('cost');
    expect($costBefore)->toBe(110_000); // 2 @ 550 EGP

    $this->inventory->changePrice($this->material, 20_000);

    // The past cost is a fact, not a live calculation — only what is left on
    // the shelf gets re-valued.
    expect($roomMaterial->fresh()->getRawOriginal('cost'))->toBe(110_000);
    expect($room->materialsCost())->toBe(110_000);
    expect($this->inventory->stockValue())->toBe(60_000); // 3 @ 200 EGP
});

test('the cashbox balance after a full round trip equals the opening balance minus what is left on the shelf', function () {
    $this->cashbox->setOpeningBalance(1_000_000, '2025-12-31');

    $this->inventory->addStock($this->material, 5_000, 55_000, '2026-01-01'); // -275,000
    $this->inventory->addStock($this->material, 2_000, 60_000, '2026-01-02'); // -120,000
    $this->inventory->reduceStock($this->material, 1_000, '2026-01-03'); //  +60,000

    expect($this->inventory->currentStock($this->material))->toBe(6_000);
    expect($this->inventory->stockValue())->toBe(360_000); // 6 @ 600 EGP
    expect($this->cashbox->balance())->toBe(665_000);
});
