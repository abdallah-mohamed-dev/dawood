<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->inventory = app(InventoryService::class);
    $this->roomMaterials = app(RoomMaterialService::class);
    $this->wood = MaterialType::query()->where('name', 'خامة')->value('id');
    $this->accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');
});

test('materials are split into a wood box and an accessories box', function () {
    Material::factory()->create(['name' => 'خشب بلوط', 'material_type_id' => $this->wood]);
    Material::factory()->create(['name' => 'مفصلة نحاس', 'material_type_id' => $this->accessory]);
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    $accessoriesBox = strpos($html, 'الاكسسوارات');
    expect(strpos($html, 'data-label="خشب بلوط'))->toBeLessThan($accessoriesBox);
    expect(strpos($html, 'data-label="مفصلة نحاس'))->toBeGreaterThan($accessoriesBox);
});

test('a completed room disables edit, issue and delete with the lock reason', function () {
    $room = Room::factory()->create(['status' => RoomStatus::Completed]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    RoomMaterial::factory()->create(['room_id' => $room->id, 'material_id' => $material->id, 'required_quantity' => 5_000]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect(substr_count($html, 'title="الغرفة مكتملة ومقفولة"'))->toBeGreaterThanOrEqual(3);
});

test('an in-progress room keeps its buttons working', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    RoomMaterial::factory()->create(['room_id' => $room->id, 'material_id' => $material->id, 'required_quantity' => 5_000]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->not->toContain('title="الغرفة مكتملة ومقفولة"');
});

test('a fully issued material is green and says so, not red', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    $this->inventory->addStock($material, 1_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $requirement = $this->roomMaterials->addRequirement($room, $material, 1_000);
    $this->roomMaterials->issue($requirement, 1_000, '2026-01-02');

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->toContain('border-success');
    expect($html)->toContain('title="تم صرف الكمية كاملة"');
    expect($html)->not->toContain('هذه الخامة ناقصة');
});

test('a material short of stock is red with the shortage message', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    $this->roomMaterials->addRequirement($room, $material, 5_000);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->toContain('border-danger');
    expect($html)->toContain('هذه الخامة ناقصة ومطلوب شراؤها');
});

test('a fully issued and short material is green, not red', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    $this->inventory->addStock($material, 1_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $requirement = $this->roomMaterials->addRequirement($room, $material, 1_000);
    $this->roomMaterials->issue($requirement, 1_000, '2026-01-02');

    // Stock is now 0 and everything is issued — "short" must not win.
    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    expect($html)->not->toContain('border-danger');
});

test('editing a requirement over HTTP saves it', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    $requirement = $this->roomMaterials->addRequirement($room, $material, 5_000);

    $this->actingAs($this->admin)
        ->patch(route('rooms.materials.update', [$room, $requirement]), ['required_quantity' => '7'])
        ->assertSessionHasNoErrors();

    expect($requirement->fresh()->getRawOriginal('required_quantity'))->toBe(7_000);
});

test('lowering a requirement below what was issued shows the Arabic reason on its own row', function () {
    $room = Room::factory()->create(['status' => RoomStatus::InProgress]);
    $material = Material::factory()->create(['material_type_id' => $this->wood]);
    $this->inventory->addStock($material, 10_000, 10_000, '2026-01-01', PaymentMethod::Cash);
    $requirement = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($requirement, 3_000, '2026-01-02');

    $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->patch(route('rooms.materials.update', [$room, $requirement]), ['required_quantity' => '1'])
        ->assertSessionHasErrors(['required_quantity' => 'الخامة دي اتصرف منها 3 بالفعل، ومينفعش المطلوب يبقى أقل من كده.'], errorBag: 'edit_'.$requirement->id);

    expect($requirement->fresh()->getRawOriginal('required_quantity'))->toBe(5_000);
});
