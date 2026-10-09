<?php

use App\Exceptions\ExceedsRequiredQuantityException;
use App\Exceptions\InsufficientStockException;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->roomMaterials = new RoomMaterialService($this->inventory);
    $this->material = Material::factory()->create();
    $this->room = Room::factory()->create();
});

test('addRequirement creates a room material requirement', function () {
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    expect($rm->getRawOriginal('required_quantity'))->toBe(5);
    expect($rm->getRawOriginal('issued_quantity'))->toBe(0);
    expect($rm->getRawOriginal('cost'))->toBe(0);
});

test('addRequirement rejects a zero or negative quantity', function () {
    $this->roomMaterials->addRequirement($this->room, $this->material, 0);
})->throws(InvalidArgumentException::class);

test('the reference scenario: two issues across two prices accumulate to the exact reference cost', function () {
    $this->inventory->addStock($this->material, 3, 550, '2026-01-01');

    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    // 3 @ 550 EGP — issued while the material was still worth 550.
    $this->roomMaterials->issue($rm, 3, '2026-01-02');

    // Re-priced to 600 before the second issue, so only the last 2 units are
    // costed at the new price.
    $this->inventory->addStock($this->material, 2, 600, '2026-01-03');
    $this->roomMaterials->issue($rm, 2, '2026-01-04');

    $rm->refresh();
    expect($rm->getRawOriginal('issued_quantity'))->toBe(5);
    expect($rm->getRawOriginal('cost'))->toBe(2_850); // 1,650 + 1,200 EGP
    expect($this->room->materialsCost())->toBe(2_850);
    expect($this->inventory->currentStock($this->material))->toBe(0);
});

test('issuing moves no money: the cashbox only ever saw the stock being added', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-01-01'); // 275,000 piastres out
    $balanceAfterAdd = $this->cashbox->balance();

    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);
    $this->roomMaterials->issue($rm, 5, '2026-01-02');

    expect($this->cashbox->balance())->toBe($balanceAfterAdd);
});

test('issuing more than available in stock is rejected and nothing changes', function () {
    $this->inventory->addStock($this->material, 3, 100, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 10);

    expect(fn () => $this->roomMaterials->issue($rm, 10, '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(3);
    expect($rm->fresh()->getRawOriginal('issued_quantity'))->toBe(0);
});

test('issuing more than the required quantity is rejected', function () {
    $this->inventory->addStock($this->material, 10, 100, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    expect(fn () => $this->roomMaterials->issue($rm, 6, '2026-01-02'))
        ->toThrow(ExceedsRequiredQuantityException::class);
});

test('issuing can happen across multiple calls, accumulating issued_quantity and cost', function () {
    $this->inventory->addStock($this->material, 10, 100, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    $this->roomMaterials->issue($rm, 2, '2026-01-02');
    $this->roomMaterials->issue($rm, 3, '2026-01-03');

    $rm->refresh();
    expect($rm->getRawOriginal('issued_quantity'))->toBe(5);
    expect($rm->getRawOriginal('cost'))->toBe(500);
});

test('a re-price between two issues changes only the second issue cost', function () {
    $this->inventory->addStock($this->material, 5, 100, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    $this->roomMaterials->issue($rm, 2, '2026-01-02'); // 20,000
    $this->inventory->changePrice($this->material, 200);
    $this->roomMaterials->issue($rm, 3, '2026-01-03'); // 60,000

    $rm->refresh();
    expect($rm->getRawOriginal('cost'))->toBe(800);
    expect($rm->getRawOriginal('issued_quantity'))->toBe(5);
});

test('removeRequirement deletes a requirement that has not been issued against', function () {
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5);

    $this->roomMaterials->removeRequirement($rm);

    expect(RoomMaterial::query()->find($rm->id))->toBeNull();
});
