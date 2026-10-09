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
    $movement = $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(5);
    expect($this->material->getRawOriginal('unit_price'))->toBe(550); // 550 EGP

    expect($movement->type)->toBe(InventoryMovementType::In);
    expect($movement->getRawOriginal('quantity'))->toBe(5);
    expect($movement->getRawOriginal('cost'))->toBe(2_750); // 2,750 EGP

    expect($this->cashbox->balance())->toBe(-2_750);
    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->kind)->toBe(CashboxTransactionKind::InventoryPurchase);
    expect($transaction->getRawOriginal('amount'))->toBe(2_750);
    // The movement is the source of truth the cashbox row points at.
    expect($transaction->source_type)->toBe(InventoryMovement::class);
    expect($transaction->source_id)->toBe($movement->id);
});

test('adding stock at a new price re-prices the whole material, not just the new quantity', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $this->inventory->addStock($this->material, 2, 600, '2026-01-02');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(7);
    expect($this->material->getRawOriginal('unit_price'))->toBe(600);
    expect($this->inventory->stockValue())->toBe(4_200); // 7 @ 600 EGP
});

test('reduceStock sells at the current price, logs a "sold" movement, and brings the money back in', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    $movement = $this->inventory->reduceStock($this->material, 2, '2026-01-02');

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(3);

    expect($movement->type)->toBe(InventoryMovementType::Sold);
    expect($movement->getRawOriginal('quantity'))->toBe(2);
    expect($movement->getRawOriginal('cost'))->toBe(1_100); // 2 @ 550 EGP

    expect($this->cashbox->balance())->toBe(-2_750 + 1_100);
    $transaction = CashboxTransaction::query()->latest('id')->first();
    expect($transaction->kind)->toBe(CashboxTransactionKind::MaterialSale);
    expect($transaction->getRawOriginal('amount'))->toBe(1_100);
});

test('the reference numbers: add 5000 @ 550, sell 2000, then re-price to 600', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $this->inventory->reduceStock($this->material, 2, '2026-01-02');

    expect($this->inventory->currentStock($this->material))->toBe(3);
    expect($this->cashbox->balance())->toBe(-1_650);

    $this->inventory->changePrice($this->material, 600);

    expect($this->inventory->stockValue())->toBe(1_800); // 3 @ 600 EGP
    expect($this->cashbox->balance())->toBe(-1_650); // a re-price moves no money
});

test('changePrice re-values the stock without touching the cashbox or the quantity', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    expect($this->inventory->stockValue())->toBe(2_750);

    $this->inventory->changePrice($this->material, 600);

    $this->material->refresh();
    expect($this->material->getRawOriginal('unit_price'))->toBe(600);
    expect($this->material->getRawOriginal('quantity'))->toBe(5);
    expect($this->inventory->stockValue())->toBe(3_000); // 5 @ 600 EGP
    expect(CashboxTransaction::query()->count())->toBe(1);
    expect($this->cashbox->balance())->toBe(-2_750);
});

test('issue prices at the current unit price, leaves stock and the cashbox otherwise', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $related = User::factory()->create();

    $result = $this->inventory->issue($this->material, 1, $related, '2026-01-02');

    expect($result)->toBe(['cost' => 550]); // 1 @ 550 EGP
    expect($this->inventory->currentStock($this->material))->toBe(4);
    expect($this->cashbox->balance())->toBe(-2_750);

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::Out)->sole();
    expect($movement->getRawOriginal('cost'))->toBe(550);
    expect($movement->related_type)->toBe(User::class);
    expect($movement->related_id)->toBe($related->id);
});

test('returning what was issued gives back the quantity at the cost it was issued at, not the new price', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $related = User::factory()->create();
    $this->inventory->issue($this->material, 1, $related, '2026-01-02');

    $this->inventory->changePrice($this->material, 600);
    $this->inventory->returnIssued($related);

    expect($this->inventory->currentStock($this->material))->toBe(5);
    expect($this->cashbox->balance())->toBe(-2_750);

    $returned = InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->sole();
    expect($returned->getRawOriginal('quantity'))->toBe(1);
    expect($returned->getRawOriginal('cost'))->toBe(550);
    expect($returned->related_type)->toBe(User::class);
    expect($returned->related_id)->toBe($related->id);

    // The original `out` is still there for audit, next to the `return`.
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Out)->count())->toBe(1);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->count())->toBe(1);
});

test('returning never touches the cashbox or the movement log of a re-priced material value', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $related = User::factory()->create();
    $this->inventory->issue($this->material, 2, $related, '2026-01-02');
    $this->inventory->changePrice($this->material, 600);

    $transactionsBefore = CashboxTransaction::query()->count();
    $this->inventory->returnIssued($related);

    expect(CashboxTransaction::query()->count())->toBe($transactionsBefore);
    // Stock is back at 5 units, but they are now worth 600 each.
    expect($this->inventory->stockValue())->toBe(3_000);
});

test('stockValueByType splits the warehouse by type and files type-less materials under "none"', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01'); // 275,000

    $other = Material::factory()->create(['material_type_id' => null]);
    $this->inventory->addStock($other, 2, 100, '2026-01-01'); // 20,000

    $result = $this->inventory->stockValueByType();

    expect($result['by_type'])->toBe([
        $this->material->material_type_id => ['label' => 'خامة', 'value' => 2_750],
        'none' => ['label' => '—', 'value' => 200],
    ]);
    expect($result['total'])->toBe(2_950);
});

test('stockValueByType only lists types that actually hold stock', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    $result = $this->inventory->stockValueByType();

    expect(array_keys($result['by_type']))->toBe([$this->material->material_type_id]);
    expect($result['total'])->toBe(2_750);
});

test('stockValueByType labels a type by its name', function () {
    $type = MaterialType::query()->firstOrCreate(['name' => 'خشب'], ['position' => 9]);
    $material = Material::factory()->create(['material_type_id' => $type->id]);
    $this->inventory->addStock($material, 1, 100, '2026-01-01');

    $result = $this->inventory->stockValueByType();

    expect($result['by_type'][$type->id]['label'])->toBe('خشب');
    expect($result['total'])->toBe(100);
});

test('stockValue ignores materials that have run out of stock', function () {
    $this->inventory->addStock($this->material, 1, 100, '2026-01-01');
    $this->inventory->reduceStock($this->material, 1, '2026-01-02');

    expect($this->inventory->stockValue())->toBe(0);
});

test('stockByMaterialIds reads every material in one query and returns raw integers', function () {
    $other = Material::factory()->create();
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $this->inventory->addStock($other, 3, 40, '2026-01-01');

    $stock = $this->inventory->stockByMaterialIds();

    expect($stock->all())->toBe([$this->material->id => 5, $other->id => 3]);
    expect($stock->get($this->material->id))->toBe(5);

    $filtered = $this->inventory->stockByMaterialIds([$other->id]);
    expect($filtered->all())->toBe([$other->id => 3]);
});

test('addStock rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 0, 100, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('addStock rejects a zero or negative unit price', function () {
    $this->inventory->addStock($this->material, 1, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('the cost of a purchase is the quantity times the price, with nothing rounded away', function () {
    // Both sides are whole numbers since specs/023, so there is no scale to
    // divide back out: 7 × 13 is exactly 91, not 91-ish.
    $movement = $this->inventory->addStock($this->material, 7, 13, '2026-01-01');

    expect($movement->getRawOriginal('cost'))->toBe(91);
    expect($this->cashbox->balance())->toBe(-91);
});

test('the smallest possible purchase still costs a pound — no cost can round to zero', function () {
    // The old ×1000 quantity scale let a tiny quantity round its cost down to
    // zero, which had to be rejected. One whole unit at one pound is now the
    // floor, and it goes through.
    $movement = $this->inventory->addStock($this->material, 1, 1, '2026-01-01');

    expect($movement->getRawOriginal('cost'))->toBe(1);
    expect($this->material->fresh()->getRawOriginal('quantity'))->toBe(1);
    expect($this->cashbox->balance())->toBe(-1);
});

test('changePrice rejects a zero or negative price and leaves the material alone', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    expect(fn () => $this->inventory->changePrice($this->material, 0))
        ->toThrow(InvalidArgumentException::class);

    expect($this->material->fresh()->getRawOriginal('unit_price'))->toBe(550);
});

test('reduceStock rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 1, 100, '2026-01-01');

    $this->inventory->reduceStock($this->material, 0, '2026-01-02');
})->throws(InvalidArgumentException::class);

test('reduceStock refuses to sell more than is on hand and writes nothing', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    expect(fn () => $this->inventory->reduceStock($this->material, 5_001, '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(5);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Sold)->count())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(1);
});

test('issue refuses to issue more than is on hand and writes nothing', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    expect(fn () => $this->inventory->issue($this->material, 5_001, User::factory()->create(), '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(5);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Out)->count())->toBe(0);
});

test('issue rejects a zero or negative quantity', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    $this->inventory->issue($this->material, 0, User::factory()->create(), '2026-01-02');
})->throws(InvalidArgumentException::class);

test('selling stock is money coming in, not revenue: the profit report does not move', function () {
    $profit = new ProfitService($this->inventory);
    $room = Room::factory()->create(['sale_price' => 3_000, 'status' => RoomStatus::Completed]);
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');

    $revenueBefore = $profit->revenue();
    $netProfitBefore = $profit->netProfit();

    $this->inventory->reduceStock($this->material, 2, '2026-01-02');

    expect($profit->revenue())->toBe($revenueBefore);
    expect($profit->netProfit())->toBe($netProfitBefore);
});

test('changePrice never rewrites what a room already paid for the same material', function () {
    $roomMaterials = new RoomMaterialService($this->inventory);
    $room = Room::factory()->create();

    $this->inventory->addStock($this->material, 5, 550, '2026-01-01');
    $roomMaterial = $roomMaterials->addRequirement($room, $this->material, 5);
    $roomMaterials->issue($roomMaterial, 2, '2026-01-02');

    $costBefore = $roomMaterial->fresh()->getRawOriginal('cost');
    expect($costBefore)->toBe(1_100); // 2 @ 550 EGP

    $this->inventory->changePrice($this->material, 200);

    // The past cost is a fact, not a live calculation — only what is left on
    // the shelf gets re-valued.
    expect($roomMaterial->fresh()->getRawOriginal('cost'))->toBe(1_100);
    expect($room->materialsCost())->toBe(1_100);
    expect($this->inventory->stockValue())->toBe(600); // 3 @ 200 EGP
});

test('the cashbox balance after a full round trip equals the opening balance minus what is left on the shelf', function () {
    $this->cashbox->setOpeningBalance(10_000, '2025-12-31');

    $this->inventory->addStock($this->material, 5, 550, '2026-01-01'); // -275,000
    $this->inventory->addStock($this->material, 2, 600, '2026-01-02'); // -120,000
    $this->inventory->reduceStock($this->material, 1, '2026-01-03'); //  +60,000

    expect($this->inventory->currentStock($this->material))->toBe(6);
    expect($this->inventory->stockValue())->toBe(3_600); // 6 @ 600 EGP
    expect($this->cashbox->balance())->toBe(6_650);
});
