<?php

use App\Enums\InventoryMovementType;
use App\Enums\RoomCostType;
use App\Exceptions\RoomHasCostsException;
use App\Models\CustomerPayment;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Room;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\InventoryService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->roomMaterials = new RoomMaterialService($this->inventory);
    $this->payments = new CustomerPaymentService($this->cashbox);
    $this->roomService = new RoomService($this->inventory, $this->payments);
    $this->material = Material::factory()->create();
    $this->room = Room::factory()->create();
});

test('deleting a room with no issued materials just deletes it', function () {
    $this->roomService->deleteRoom($this->room, false);

    expect(Room::query()->find($this->room->id))->toBeNull();
});

test('deleting a room also removes its payments and their cashbox transactions', function () {
    $this->payments->create($this->room, 500_000, '2026-01-01');
    expect($this->cashbox->balance())->toBe(500_000);

    $this->roomService->deleteRoom($this->room, false);

    expect(CustomerPayment::query()->count())->toBe(0);
    expect($this->cashbox->balance())->toBe(0);
});

test('deleting a room with the "return" choice returns materials to their original batches', function () {
    $batchA = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $batchB = $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-03');

    expect($this->inventory->currentStock($this->material))->toBe(8_000);

    $this->roomService->deleteRoom($this->room, true);

    expect($this->inventory->currentStock($this->material))->toBe(13_000);
    expect($batchA->fresh()->getRawOriginal('remaining_quantity'))->toBe(3_000);
    expect($batchB->fresh()->getRawOriginal('remaining_quantity'))->toBe(10_000);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->count())->toBe(2);
    expect(Room::query()->find($this->room->id))->toBeNull();
});

test('deleting a room with the "consumed" choice does not touch stock', function () {
    $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');
    $rm = $this->roomMaterials->addRequirement($this->room, $this->material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-03');

    $this->roomService->deleteRoom($this->room, false);

    expect($this->inventory->currentStock($this->material))->toBe(8_000);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->count())->toBe(0);
    expect(Room::query()->find($this->room->id))->toBeNull();
});

test('deleting a room that carries labour or extra costs is refused outright', function () {
    $costs = new RoomCostService($this->cashbox);
    $costs->create($this->room, RoomCostType::Labor, 500_000, '2026-01-05');

    expect(fn () => $this->roomService->deleteRoom($this->room, false))
        ->toThrow(RoomHasCostsException::class);

    expect(Room::query()->whereKey($this->room->id)->exists())->toBeTrue();
    // The money that left the drawer stays gone — nothing was silently refunded.
    expect($this->cashbox->totalOut())->toBe(500_000);
});

test('the refusal happens before anything is touched, so payments and materials survive', function () {
    $costs = new RoomCostService($this->cashbox);
    $this->payments->create($this->room, 300_000, '2026-01-01');
    $costs->create($this->room, RoomCostType::Other, 100_000, '2026-01-05');

    expect(fn () => $this->roomService->deleteRoom($this->room, true))
        ->toThrow(RoomHasCostsException::class);

    expect(CustomerPayment::query()->count())->toBe(1);
    expect($this->cashbox->balance())->toBe(200_000);
});
