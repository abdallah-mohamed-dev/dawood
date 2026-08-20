<?php

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\User;
use App\Services\InventoryService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->customer = Customer::factory()->create();
});

test('guests cannot access room pages', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->get(route('rooms.show', $room))->assertRedirect(route('login'));
});

test('a room can be created for a customer', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.store'), [
        'customer_id' => $this->customer->id,
        'room_type' => 'غرفة نوم',
        'sale_price' => '30000.00',
    ]);

    $room = Room::query()->where('customer_id', $this->customer->id)->sole();
    $response->assertRedirect(route('rooms.show', $room));
    expect($room->getRawOriginal('sale_price'))->toBe(3_000_000);
    expect($room->status)->toBe(RoomStatus::Draft);
});

test('creating a room with an unsafely large sale_price is rejected as a clean validation error, not a 500', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.store'), [
        'customer_id' => $this->customer->id,
        'room_type' => 'غرفة نوم',
        'sale_price' => '9999999999999999.00', // 16 digits — over ScaledIntegerCast's safe limit
    ]);

    $response->assertSessionHasErrors('sale_price');
    expect(Room::query()->count())->toBe(0);
});

test('adding a requirement with an unsafely large quantity is rejected as a clean validation error, not a 500', function () {
    $material = Material::factory()->create();
    $room = Room::factory()->for($this->customer)->create();

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '9999999999999999.000',
    ]);

    $response->assertSessionHasErrors('required_quantity');
    expect(RoomMaterial::query()->count())->toBe(0);
});

test('issuing an unsafely large quantity is rejected as a clean validation error, not a 500', function () {
    $material = Material::factory()->create();
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), [
        'quantity' => '9999999999999999.000',
    ]);

    $response->assertSessionHasErrors('quantity');
    expect($roomMaterial->fresh()->getRawOriginal('issued_quantity'))->toBe(0);
});

test('creating a room without a customer fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.store'), [
        'customer_id' => '',
        'room_type' => 'غرفة نوم',
        'sale_price' => '30000.00',
    ]);

    $response->assertSessionHasErrors('customer_id');
});

test('the room show page reflects required, issued, cost, and materials cost after an issue', function () {
    $material = Material::factory()->create(['name' => 'لوح MDF']);
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 3_000_000]);

    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), [
        'quantity' => '5',
    ]);

    $response = $this->actingAs($this->admin)->get(route('rooms.show', $room));

    $response->assertOk();
    expect($roomMaterial->fresh()->getRawOriginal('issued_quantity'))->toBe(5_000);
    expect($roomMaterial->fresh()->getRawOriginal('cost'))->toBe(50_000);
    expect($room->fresh()->materialsCost())->toBe(50_000);
});

test('issuing more than available stock shows a friendly error and changes nothing', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 3_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '10',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), [
        'quantity' => '10',
    ]);

    $response->assertSessionHas('error');
    expect($roomMaterial->fresh()->getRawOriginal('issued_quantity'))->toBe(0);
    expect(app(InventoryService::class)->currentStock($material))->toBe(3_000);
});

test('issuing more than required is rejected with a friendly error', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), [
        'quantity' => '6',
    ]);

    $response->assertSessionHas('error');
});

test('issuing a zero quantity is rejected with a message about the quantity, not the required limit', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), [
        'quantity' => '0',
    ]);

    $response->assertSessionHasErrors('quantity');
    $response->assertSessionMissing('error');
});

test('adding the same material twice to a room is rejected', function () {
    $material = Material::factory()->create();
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);

    $response = $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '3',
    ]);

    $response->assertSessionHasErrors('material_id');
    expect(RoomMaterial::query()->count())->toBe(1);
});

test('removing a material requirement that has not been issued against works', function () {
    $material = Material::factory()->create();
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();

    $this->actingAs($this->admin)->delete(route('rooms.materials.destroy', [$room, $roomMaterial]))
        ->assertRedirect();

    expect(RoomMaterial::query()->find($roomMaterial->id))->toBeNull();
});

test('the room status can be updated', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.status.update', $room), [
        'status' => 'completed',
    ])->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Completed);
});

test('an invalid status value is rejected', function () {
    $room = Room::factory()->for($this->customer)->create();

    $response = $this->actingAs($this->admin)->post(route('rooms.status.update', $room), [
        'status' => 'not-a-real-status',
    ]);

    $response->assertSessionHasErrors('status');
});

test('a room with no issued materials can be deleted directly', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->delete(route('rooms.destroy', $room))
        ->assertRedirect(route('customers.show', $this->customer));

    expect(Room::query()->find($room->id))->toBeNull();
});

test('deleting a room with issued materials requires the return_materials choice', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();
    $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), ['quantity' => '5']);

    $response = $this->actingAs($this->admin)->delete(route('rooms.destroy', $room));

    $response->assertSessionHasErrors('return_materials');
    expect(Room::query()->find($room->id))->not->toBeNull();
});

test('deleting a room and choosing to return materials restores stock', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();
    $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), ['quantity' => '5']);

    $response = $this->actingAs($this->admin)->delete(route('rooms.destroy', $room), [
        'return_materials' => '1',
    ]);

    $response->assertRedirect(route('customers.show', $this->customer));
    expect(app(InventoryService::class)->currentStock($material))->toBe(10_000);
});

test('deleting a room and choosing "consumed" does not restore stock', function () {
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.materials.store', $room), [
        'material_id' => $material->id,
        'required_quantity' => '5',
    ]);
    $roomMaterial = RoomMaterial::query()->sole();
    $this->actingAs($this->admin)->post(route('rooms.materials.issue', [$room, $roomMaterial]), ['quantity' => '5']);

    $this->actingAs($this->admin)->delete(route('rooms.destroy', $room), [
        'return_materials' => '0',
    ]);

    expect(app(InventoryService::class)->currentStock($material))->toBe(5_000);
});
