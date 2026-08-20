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
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);

    expect($rm->getRawOriginal('required_quantity'))->toBe(5_000);
    expect($rm->getRawOriginal('issued_quantity'))->toBe(0);
});

test('addRequirement rejects a zero or negative quantity', function () {
    $this->roomMaterials->addRequirement($this->room, $this->material, 0);
})->throws(InvalidArgumentException::class);

test('the full reference scenario: issuing 5 boards against a requirement produces the exact reference numbers', function () {
    $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01'); // 3 @ 100 EGP
    $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02'); // 10 @ 120 EGP

    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-03');

    $rm->refresh();
    expect($rm->getRawOriginal('issued_quantity'))->toBe(5_000);
    expect($rm->getRawOriginal('cost'))->toBe(54_000); // 540 EGP
    expect($this->room->materialsCost())->toBe(54_000);
    expect($this->inventory->currentStock($this->material))->toBe(8_000);
});

test('issuing more than available in stock is rejected and nothing changes', function () {
    $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 10_000);

    expect(fn () => $this->roomMaterials->issue($rm, 10_000, '2026-01-02'))
        ->toThrow(InsufficientStockException::class);

    expect($this->inventory->currentStock($this->material))->toBe(3_000);
    expect($rm->fresh()->getRawOriginal('issued_quantity'))->toBe(0);
});

test('issuing more than the required quantity is rejected', function () {
    $this->inventory->purchase($this->material, 10_000, 10_000, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);

    expect(fn () => $this->roomMaterials->issue($rm, 6_000, '2026-01-02'))
        ->toThrow(ExceedsRequiredQuantityException::class);
});

test('issuing can happen across multiple calls, accumulating issued_quantity and cost', function () {
    $this->inventory->purchase($this->material, 10_000, 10_000, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);

    $this->roomMaterials->issue($rm, 2_000, '2026-01-02');
    $this->roomMaterials->issue($rm, 3_000, '2026-01-03');

    $rm->refresh();
    expect($rm->getRawOriginal('issued_quantity'))->toBe(5_000);
    expect($rm->getRawOriginal('cost'))->toBe(50_000);
});

test('removeRequirement deletes a requirement that has not been issued against', function () {
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);

    $this->roomMaterials->removeRequirement($rm);

    expect(RoomMaterial::query()->find($rm->id))->toBeNull();
});

test('removeRequirement rejects a requirement that has already been issued against', function () {
    $this->inventory->purchase($this->material, 10_000, 10_000, '2026-01-01');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);
    $this->roomMaterials->issue($rm, 1_000, '2026-01-02');

    expect(fn () => $this->roomMaterials->removeRequirement($rm))
        ->toThrow(InvalidArgumentException::class);

    expect(RoomMaterial::query()->find($rm->id))->not->toBeNull();
});
