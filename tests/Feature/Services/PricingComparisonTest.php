<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->inventory = app(InventoryService::class);
    $this->roomMaterials = app(RoomMaterialService::class);
    $this->profit = app(ProfitService::class);

    $wood = MaterialType::query()->where('name', 'خامة')->value('id');
    $accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');
    $this->woodMaterial = Material::factory()->create(['material_type_id' => $wood]);
    $this->accessoryMaterial = Material::factory()->create(['material_type_id' => $accessory]);

    $this->inventory->addStock($this->woodMaterial, 10, 100, '2026-01-01', PaymentMethod::Cash); // 100.00 EGP / unit
    $this->inventory->addStock($this->accessoryMaterial, 10, 200, '2026-01-01', PaymentMethod::Cash); // 200.00 EGP / unit

    $this->room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $wood = $this->roomMaterials->addRequirement($this->room, $this->woodMaterial, 5);
    $this->roomMaterials->issue($wood, 5, '2026-01-02'); // 500.00 EGP
    $accessory = $this->roomMaterials->addRequirement($this->room, $this->accessoryMaterial, 2);
    $this->roomMaterials->issue($accessory, 2, '2026-01-02'); // 400.00 EGP

    $costs = app(RoomCostService::class);
    $costs->create($this->room, RoomCostType::Labor, 3_000, '2026-01-03'); // 3000.00 EGP
    $costs->create($this->room, RoomCostType::Other, 1_000, '2026-01-04'); // 1000.00 EGP
});

test('each cost line is compared with its own estimate, with the sign kept', function () {
    $this->room->update([
        'status' => RoomStatus::Completed,
        'estimated_materials' => 600,    // 600.00 — actual 500.00
        'estimated_accessories' => 300,  // 300.00 — actual 400.00
        'estimated_labor' => null,          // not estimated
        'estimated_other' => 500,       // 500.00 — actual 1000.00
    ]);

    $comparison = $this->profit->pricingComparison($this->room->fresh());

    expect($comparison['materials'])->toBe(['estimated' => 600, 'actual' => 500, 'difference' => -100]);
    expect($comparison['accessories'])->toBe(['estimated' => 300, 'actual' => 400, 'difference' => 100]);
    expect($comparison['labor'])->toBe(['estimated' => null, 'actual' => 3_000, 'difference' => null]);
    expect($comparison['other'])->toBe(['estimated' => 500, 'actual' => 1_000, 'difference' => 500]);
});

test('the total is only compared when every line was estimated', function () {
    $this->room->update([
        'status' => RoomStatus::Completed,
        'estimated_materials' => 600,
        'estimated_accessories' => 300,
        'estimated_labor' => null,
        'estimated_other' => 500,
    ]);

    $total = $this->profit->pricingComparison($this->room->fresh())['total'];

    expect($total['estimated'])->toBeNull();
    expect($total['difference'])->toBeNull();
    expect($total['actual'])->toBe(4_900);

    $this->room->update(['estimated_labor' => 3_000]);

    $total = $this->profit->pricingComparison($this->room->fresh())['total'];

    // Estimates 600 + 300 + 3000 + 500 = 4400. Actuals 500 + 400 + 3000 + 1000 = 4900.
    expect($total)->toBe(['estimated' => 4_400, 'actual' => 4_900, 'difference' => 500]);
});

test('a room with no estimates has no difference anywhere', function () {
    $total = $this->profit->pricingComparison($this->room->fresh())['total'];

    expect($total['estimated'])->toBeNull();
    expect($total['difference'])->toBeNull();
});

test('a room with no materials and no costs has zero actuals', function () {
    $empty = Room::factory()->create(['status' => RoomStatus::Completed]);

    $comparison = $this->profit->pricingComparison($empty);

    expect($comparison['materials']['actual'])->toBe(0);
    expect($comparison['accessories']['actual'])->toBe(0);
    expect($comparison['labor']['actual'])->toBe(0);
    expect($comparison['other']['actual'])->toBe(0);
});

test('materialsCostByType separates wood from accessories', function () {
    $byType = $this->room->fresh()->materialsCostByType();
    $wood = MaterialType::query()->where('name', 'خامة')->value('id');
    $accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');

    expect($byType[$wood])->toBe(500);
    expect($byType[$accessory])->toBe(400);
});

test('the duration counts both the start and the end day', function () {
    $this->room->update(['status' => RoomStatus::Completed, 'started_at' => '2026-03-01', 'completed_at' => '2026-03-10', 'expected_duration_days' => 8]);

    expect($this->profit->durationComparison($this->room->fresh()))->toBe(['expected' => 8, 'actual' => 10, 'difference' => 2]);
});

test('a room started and finished on the same day takes one day, not zero', function () {
    $this->room->update(['started_at' => '2026-03-01', 'completed_at' => '2026-03-01']);

    expect($this->profit->durationComparison($this->room->fresh())['actual'])->toBe(1);
});

test('without a start date the actual duration is null', function () {
    $this->room->update(['completed_at' => '2026-03-10']);

    expect($this->profit->durationComparison($this->room->fresh())['actual'])->toBeNull();
});

test('the comparison table only appears once the room is completed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('rooms.show', $this->room))->assertOk()
        ->assertDontSee('التقدير مقابل الفعلي');

    $this->room->update(['status' => RoomStatus::Completed]);

    $this->actingAs($user)->get(route('rooms.show', $this->room))->assertOk()
        ->assertSee('التقدير مقابل الفعلي');
});
