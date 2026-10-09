<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->customer = Customer::factory()->create();
});

test('the sidebar shows the five money figures', function () {
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 30_000]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)
        ->toContain('سعر البيع')
        ->toContain('تكلفة الخامات')
        ->toContain('مصنعية + أخرى')
        ->toContain('إجمالي التكلفة')
        ->toContain('الربح المتوقع');
});

test('the sale price is not duplicated across the page', function () {
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 30_000]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect(substr_count($html, '<span class="text-secondary">سعر البيع</span>'))->toBe(1);
});

test('materials of both types sit in one table', function () {
    $wood = MaterialType::query()->where('name', 'خامة')->value('id');
    $accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');
    $woodMaterial = Material::factory()->create(['name' => 'خشب أرو', 'material_type_id' => $wood]);
    $accessoryMaterial = Material::factory()->create(['name' => 'يد باب', 'material_type_id' => $accessory]);
    $room = Room::factory()->for($this->customer)->create();
    RoomMaterial::factory()->for($room)->for($woodMaterial)->create();
    RoomMaterial::factory()->for($room)->for($accessoryMaterial)->create();

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->toContain('خشب أرو')->toContain('يد باب');
});

test('labour and extra-expense costs sit in one table with a type chip', function () {
    $room = Room::factory()->for($this->customer)->create();
    RoomCost::factory()->for($room)->create(['type' => RoomCostType::Labor, 'description' => 'دفعة نجار']);
    RoomCost::factory()->for($room)->create(['type' => RoomCostType::Other, 'description' => 'نولون']);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)
        ->toContain('دفعة نجار')
        ->toContain('نولون')
        ->toContain('مصنعية')
        ->toContain('مصروف إضافي');
});

test('the empty materials-by-type chart is gone', function () {
    $room = Room::factory()->for($this->customer)->create();

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->not->toContain('تكلفة الخامات حسب النوع');
});

test('each activity entry is one row with its event as a badge', function () {
    $room = Room::factory()->for($this->customer)->create(['room_type' => 'غرفة نوم']);
    $room->update(['room_type' => 'غرفة سفرة']);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    // The event label carries a wash background, like the room status tag.
    expect($html)->toContain('bg-warning/10 text-warning');

    // The change detail sits on the same row, not pushed onto its own line.
    expect($html)->not->toContain('w-full whitespace-pre-line');

    $row = substr($html, strpos($html, 'نوع الغرفة: غرفة نوم') - 1200, 1400);
    expect($row)->toContain('تعديل');
});

test('row actions are real buttons, disabled once a material is fully issued', function () {
    $material = Material::factory()->create();
    $inventory = app(InventoryService::class);
    $roomMaterials = app(RoomMaterialService::class);
    $inventory->addStock($material, 1, 100, '2026-01-01', PaymentMethod::Cash);
    $room = Room::factory()->for($this->customer)->create();
    $requirement = $roomMaterials->addRequirement($room, $material, 1);
    $roomMaterials->issue($requirement, 1, '2026-01-02');

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)
        ->toContain('<button')
        ->toContain('title="تم صرف الكمية كاملة"');
});
