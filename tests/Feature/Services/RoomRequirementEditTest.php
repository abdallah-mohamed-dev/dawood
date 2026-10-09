<?php

use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Exceptions\ExceedsRequiredQuantityException;
use App\Exceptions\RoomLockedException;
use App\Models\CashboxTransaction;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->inventory = app(InventoryService::class);
    $this->roomMaterials = app(RoomMaterialService::class);
    $this->profit = app(ProfitService::class);
    $this->material = Material::factory()->create(['unit_price' => 100]);
    $this->room = Room::factory()->create(['status' => RoomStatus::InProgress]);

    // 10 units in stock, 5 needed by the room, 1 already issued.
    $this->inventory->addStock($this->material, 10, 100, '2026-01-01', PaymentMethod::Cash);
    $this->requirement = $this->roomMaterials->addRequirement($this->room, $this->material, 5);
    $this->roomMaterials->issue($this->requirement, 1, '2026-01-02');
    $this->requirement->refresh();
});

test('the requirement can be changed while the room is in progress', function () {
    $this->roomMaterials->updateRequirement($this->requirement, 3);

    expect($this->requirement->fresh()->getRawOriginal('required_quantity'))->toBe(3);
});

test('lowering the requirement below what was issued is refused and changes nothing', function () {
    // Issue two more so there is room to aim below the issued total: 3 out of
    // 5 are now on the floor, and asking for 2 has to be refused. Quantities
    // are whole units since specs/023, so there is no fraction to aim at.
    $this->roomMaterials->issue($this->requirement, 2, '2026-01-03');

    expect(fn () => $this->roomMaterials->updateRequirement($this->requirement, 2))
        ->toThrow(ExceedsRequiredQuantityException::class);

    expect($this->requirement->fresh()->getRawOriginal('required_quantity'))->toBe(5);
});

test('setting the requirement to exactly the issued amount is allowed', function () {
    $this->roomMaterials->updateRequirement($this->requirement, 1);

    expect($this->requirement->fresh()->getRawOriginal('required_quantity'))->toBe(1);
});

test('an update does not change issued quantity or cost', function () {
    $issued = $this->requirement->getRawOriginal('issued_quantity');
    $cost = $this->requirement->getRawOriginal('cost');

    $this->roomMaterials->updateRequirement($this->requirement, 4);

    expect($this->requirement->fresh()->getRawOriginal('issued_quantity'))->toBe($issued);
    expect($this->requirement->fresh()->getRawOriginal('cost'))->toBe($cost);
});

test('editing, removing and issuing on a completed room are all refused', function () {
    $this->room->update(['status' => RoomStatus::Completed]);

    expect(fn () => $this->roomMaterials->updateRequirement($this->requirement, 4))
        ->toThrow(RoomLockedException::class);
    expect(fn () => $this->roomMaterials->removeRequirement($this->requirement))
        ->toThrow(RoomLockedException::class);
    expect(fn () => $this->roomMaterials->issue($this->requirement, 500, '2026-01-03'))
        ->toThrow(RoomLockedException::class);
});

test('removing an issued requirement puts the issued quantity back in stock', function () {
    $before = $this->inventory->currentStock($this->material);

    $this->roomMaterials->removeRequirement($this->requirement);

    expect($this->inventory->currentStock($this->material))->toBe($before + 1);
    expect(RoomMaterial::query()->find($this->requirement->id))->toBeNull();
});

test('removing an issued requirement records a return movement', function () {
    $this->roomMaterials->removeRequirement($this->requirement);

    expect(InventoryMovement::query()->where('type', InventoryMovementType::ReturnedToStock)->count())->toBe(1);
});

test('removing a requirement that was never issued works without any stock movement', function () {
    $fresh = $this->roomMaterials->addRequirement($this->room, Material::factory()->create(), 2_000);
    $movements = InventoryMovement::query()->count();

    $this->roomMaterials->removeRequirement($fresh);

    expect(RoomMaterial::query()->find($fresh->id))->toBeNull();
    expect(InventoryMovement::query()->count())->toBe($movements);
});

test('removing an issued requirement does not touch the cashbox', function () {
    $balance = app(CashboxService::class)->balance();
    $rows = CashboxTransaction::query()->count();

    $this->roomMaterials->removeRequirement($this->requirement);

    expect(app(CashboxService::class)->balance())->toBe($balance);
    expect(CashboxTransaction::query()->count())->toBe($rows);
});

test('the room profit does not change while the room is not completed', function () {
    $before = $this->profit->netProfit();

    $this->roomMaterials->updateRequirement($this->requirement, 4);
    $this->roomMaterials->removeRequirement($this->requirement);

    expect($this->profit->netProfit())->toBe($before);
});
