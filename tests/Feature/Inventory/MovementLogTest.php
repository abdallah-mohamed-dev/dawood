<?php

use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->inventory = app(InventoryService::class);
    $this->roomMaterials = app(RoomMaterialService::class);

    $this->material = Material::factory()->create([
        'name' => 'خشب سرو',
        'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id'),
        'unit_price' => 0,
        'quantity' => 0,
    ]);
});

test('adding stock shows as an incoming movement with the right cost', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-03-01', PaymentMethod::Cash);

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index'))
        ->assertOk()
        ->assertSee('وارد')
        ->assertSee('2,750');
});

test('issuing to a room shows as outgoing with the room name', function () {
    $room = Room::factory()->create(['room_type' => 'مكتب']);
    $this->inventory->addStock($this->material, 5, 550, '2026-03-01', PaymentMethod::Cash);
    $requirement = $this->roomMaterials->addRequirement($room, $this->material, 2);
    $this->roomMaterials->issue($requirement, 1, '2026-03-02');

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index'))
        ->assertOk()
        ->assertSee('صادر')
        ->assertSee('مكتب');
});

test('returning issued stock shows as returned', function () {
    $room = Room::factory()->create();
    $this->inventory->addStock($this->material, 5, 550, '2026-03-01', PaymentMethod::Cash);
    $requirement = $this->roomMaterials->addRequirement($room, $this->material, 2);
    $this->roomMaterials->issue($requirement, 1, '2026-03-02');
    $this->inventory->returnIssued($requirement);

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index'))
        ->assertOk()
        ->assertSee('مرتجع');
});

test('a manual reduction shows as a sale', function () {
    $this->inventory->addStock($this->material, 5, 550, '2026-03-01', PaymentMethod::Cash);
    $this->inventory->reduceStock($this->material, 1, '2026-03-02', PaymentMethod::Cash);

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index', ['type' => InventoryMovementType::Sold->value]))
        ->assertOk()
        ->assertSee('بيع');
});

test('the filters narrow the log by material, type and date', function () {
    $other = Material::factory()->create([
        'name' => 'زجاج',
        'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id'),
    ]);
    $this->inventory->addStock($this->material, 1, 100, '2026-03-01', PaymentMethod::Cash);
    $this->inventory->addStock($other, 1, 200, '2026-04-01', PaymentMethod::Cash);

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index', ['q' => 'زجاج']))
        ->assertSee('زجاج')
        ->assertDontSee('خشب سرو');

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index', ['from' => '2026-03-15', 'to' => '2026-04-30']))
        ->assertSee('زجاج')
        ->assertDontSee('خشب سرو');
});

test('month separators appear when the month changes', function () {
    $this->inventory->addStock($this->material, 1, 100, '2026-03-01', PaymentMethod::Cash);
    $this->inventory->addStock($this->material, 1, 100, '2026-04-01', PaymentMethod::Cash);

    expect(InventoryMovement::query()->count())->toBe(2);

    $this->actingAs($this->admin)
        ->get(route('inventory.movements.index'))
        ->assertOk()
        ->assertSee('2026-03')
        ->assertSee('2026-04');
});
