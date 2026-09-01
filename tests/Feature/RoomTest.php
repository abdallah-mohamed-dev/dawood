<?php

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\User;
use App\Services\CashboxService;
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

    // Per-row bag — the room page renders one issue form per material.
    $response->assertSessionHasErrors('quantity', null, 'issue_'.$roomMaterial->id);
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

    $response->assertSessionHasErrors('quantity', null, 'issue_'.$roomMaterial->id);
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

test('a labour payment can be added from the room page and leaves the cashbox', function () {
    $room = Room::factory()->for($this->customer)->create();

    $response = $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'description' => 'دفعة أولى للنجار',
        'amount' => '5000.00',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect();
    $cost = RoomCost::query()->sole();
    expect($cost->type)->toBe(RoomCostType::Labor);
    expect($cost->getRawOriginal('amount'))->toBe(500_000);
    expect(app(CashboxService::class)->totalOut())->toBe(500_000);
});

test('an extra room expense can be added from the room page', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'other',
        'description' => 'نقل',
        'amount' => '250.50',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ]);

    expect(RoomCost::query()->sole()->type)->toBe(RoomCostType::Other);
});

test('an invalid room cost amount is reported in its own error bag, not the payment form', function () {
    $room = Room::factory()->for($this->customer)->create();

    $response = $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'amount' => 'abc',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ]);

    // The payment form shares the field name `amount` and lives on the same
    // page — its (default) bag must stay clean.
    $response->assertSessionHasErrors(['amount'], null, 'roomCost_labor');
    $response->assertSessionDoesntHaveErrors(['amount'], null, 'default');
    expect(RoomCost::query()->count())->toBe(0);
});

test('the other-expense form uses its own error bag too', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'other',
        'amount' => '',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['amount'], null, 'roomCost_other');
});

test('a zero room cost is rejected', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'amount' => '0',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['amount'], null, 'roomCost_labor');

    expect(RoomCost::query()->count())->toBe(0);
});

test('a room cost cannot be deleted through another room', function () {
    $room = Room::factory()->for($this->customer)->create();
    $other = Room::factory()->for($this->customer)->create();
    $cost = RoomCost::factory()->for($room)->create();

    $this->actingAs($this->admin)
        ->delete(route('rooms.costs.destroy', [$other, $cost]))
        ->assertNotFound();

    expect(RoomCost::query()->count())->toBe(1);
});

test('deleting a room cost puts the money back in the cashbox', function () {
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'amount' => '5000.00',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ]);
    $cost = RoomCost::query()->sole();

    $this->actingAs($this->admin)->delete(route('rooms.costs.destroy', [$room, $cost]));

    expect(RoomCost::query()->count())->toBe(0);
    expect(app(CashboxService::class)->totalOut())->toBe(0);
});

test('a room carrying costs cannot be deleted', function () {
    $room = Room::factory()->for($this->customer)->create();
    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'amount' => '5000.00',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->admin)->delete(route('rooms.destroy', $room));

    $response->assertSessionHas('error');
    expect(Room::query()->whereKey($room->id)->exists())->toBeTrue();
    // The cashbox row survives with it — the money really did leave.
    expect(app(CashboxService::class)->totalOut())->toBe(500_000);
});

test('a room can be deleted once its costs are removed', function () {
    $room = Room::factory()->for($this->customer)->create();
    $cost = RoomCost::factory()->for($room)->create();

    $this->actingAs($this->admin)->delete(route('rooms.costs.destroy', [$room, $cost]));
    $this->actingAs($this->admin)->delete(route('rooms.destroy', $room));

    expect(Room::query()->whereKey($room->id)->exists())->toBeFalse();
});

test('the room page shows the profit breakdown', function () {
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 3_000_000]);
    RoomCost::factory()->for($room)->create(['amount' => 500_000]);

    $this->actingAs($this->admin)
        ->get(route('rooms.show', $room))
        ->assertOk()
        ->assertSee('المصنعية')
        ->assertSee('مصروفات إضافية')
        ->assertSee('الربح المتوقع');
});

test('a completed room shows the profit card without the expected label', function () {
    $room = Room::factory()->for($this->customer)->create([
        'sale_price' => 3_000_000,
        'status' => RoomStatus::Completed,
    ]);

    $this->actingAs($this->admin)
        ->get(route('rooms.show', $room))
        ->assertOk()
        ->assertDontSee('الربح المتوقع');
});

test('a failed labour submission does not repopulate the extra-expense form', function () {
    $room = Room::factory()->for($this->customer)->create();

    // Both sections render the same partial and share the field name
    // `description`, so an unguarded old() would echo this value into both.
    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.costs.store', $room), [
            'type' => 'labor',
            'description' => 'دفعة النجار الأولى',
            'amount' => '',
            'occurred_at' => '2026-01-05',
            'payment_method' => 'cash',
        ])
        ->getContent();

    $labor = substr($html, strpos($html, 'roomCost_labor_description'), 400);
    $other = substr($html, strpos($html, 'roomCost_other_description'), 400);

    expect($labor)->toContain('دفعة النجار الأولى');
    expect($other)->not->toContain('دفعة النجار الأولى');
});

test('a failed submission keeps the amount and date the user typed in that section only', function () {
    $room = Room::factory()->for($this->customer)->create();

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.costs.store', $room), [
            'type' => 'other',
            'description' => '',
            'amount' => 'abc',
            'occurred_at' => '2026-02-20',
            'payment_method' => 'cash',
        ])
        ->getContent();

    $other = substr($html, strpos($html, 'roomCost_other_amount'), 400);
    $labor = substr($html, strpos($html, 'roomCost_labor_amount'), 400);

    expect($other)->toContain('value="abc"');
    expect($labor)->toContain('value=""');
    expect(substr($html, strpos($html, 'roomCost_other_occurred_at'), 400))->toContain('value="2026-02-20"');
    // The untouched section keeps today's date, not the other form's date.
    expect(substr($html, strpos($html, 'roomCost_labor_occurred_at'), 400))
        ->toContain('value="'.now()->toDateString().'"');
});

test('a failed material submission leaves both cost forms untouched', function () {
    $room = Room::factory()->for($this->customer)->create();
    $material = Material::factory()->create();

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.materials.store', $room), [
            'material_id' => $material->id,
            'required_quantity' => 'abc',
        ])
        ->getContent();

    expect(substr($html, strpos($html, 'roomCost_labor_amount'), 400))->toContain('value=""');
    expect(substr($html, strpos($html, 'roomCost_other_amount'), 400))->toContain('value=""');
    expect(substr($html, strpos($html, 'roomCost_labor_description'), 400))->toContain('value=""');
});

test('a cost validation error renders inside its own section only', function () {
    $room = Room::factory()->for($this->customer)->create();

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.costs.store', $room), [
            'type' => 'other',
            'amount' => 'abc',
            'occurred_at' => '2026-01-05',
            'payment_method' => 'cash',
        ])
        ->getContent();

    // The field name `amount` is rendered three times on this page (labour,
    // extra expense, customer payment) — the message must appear once.
    expect(substr_count($html, 'صيغة حقل المبلغ غير صحيحة.'))->toBe(1);
    expect(substr($html, strpos($html, 'roomCost_other_amount'), 600))->toContain('صيغة حقل المبلغ غير صحيحة.');
});

test('the controller-level amount error also lands in the right section only', function () {
    $room = Room::factory()->for($this->customer)->create();

    // Passes the request's regex but overflows ScaledIntegerCast's safe limit,
    // so the message comes from RoomController, not the Form Request.
    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.costs.store', $room), [
            'type' => 'labor',
            'amount' => '9999999999999999.00',
            'occurred_at' => '2026-01-05',
            'payment_method' => 'cash',
        ])
        ->getContent();

    expect(substr_count($html, 'قيمة المبلغ غير صالحة.'))->toBe(1);
    expect(substr($html, strpos($html, 'roomCost_labor_amount'), 600))->toContain('قيمة المبلغ غير صالحة.');
    expect(RoomCost::query()->count())->toBe(0);
});

test('a failed customer payment does not surface an error inside the cost forms', function () {
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 100_000]);

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.payments.store', $room), [
            'amount' => '99999.00', // more than the sale price
            'paid_at' => '2026-01-05',
            'payment_method' => 'cash',
        ])
        ->getContent();

    expect($html)->toContain('المتبقي الفعلي');
    expect(substr($html, strpos($html, 'roomCost_labor_amount'), 600))->not->toContain('المتبقي الفعلي');
    expect(substr($html, strpos($html, 'roomCost_other_amount'), 600))->not->toContain('المتبقي الفعلي');
});

test('an invalid issue quantity reports under its own material row only', function () {
    $room = Room::factory()->for($this->customer)->create();
    $inventory = app(InventoryService::class);

    $first = Material::factory()->create(['name' => 'خشب زان']);
    $second = Material::factory()->create(['name' => 'مسامير']);
    $inventory->purchase($first, 10_000, 10_000, '2026-01-01');
    $inventory->purchase($second, 10_000, 10_000, '2026-01-01');

    $firstRow = RoomMaterial::factory()->for($room)->for($first)->create(['required_quantity' => 5_000]);
    RoomMaterial::factory()->for($room)->for($second)->create(['required_quantity' => 5_000]);

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.materials.issue', [$room, $firstRow]), ['quantity' => 'abc'])
        ->getContent();

    // The page renders two rows, each with a field called `quantity` — the
    // message and the rejected value must appear exactly once.
    expect(substr_count($html, 'صيغة حقل الكمية غير صحيحة.'))->toBe(1);
    expect(substr_count($html, 'value="abc"'))->toBe(1);
});

test('a zero issue quantity is reported instead of silently reloading', function () {
    $room = Room::factory()->for($this->customer)->create();
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $row = RoomMaterial::factory()->for($room)->for($material)->create(['required_quantity' => 5_000]);

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.materials.issue', [$room, $row]), ['quantity' => '0'])
        ->getContent();

    expect($html)->toContain('يجب أن تكون الكمية أكبر من صفر.');
    expect($row->fresh()->getRawOriginal('issued_quantity'))->toBe(0);
});

test('an issue error does not leak into the cost or payment forms', function () {
    $room = Room::factory()->for($this->customer)->create();
    $material = Material::factory()->create();
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-01');
    $row = RoomMaterial::factory()->for($room)->for($material)->create(['required_quantity' => 5_000]);

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.materials.issue', [$room, $row]), ['quantity' => 'abc'])
        ->getContent();

    expect(substr($html, strpos($html, 'roomCost_labor_amount'), 600))->toContain('value=""');
    expect(substr($html, strpos($html, 'roomCost_other_amount'), 600))->toContain('value=""');
    expect(substr($html, strpos($html, 'صيغة حقل الكمية غير صحيحة.') - 2000, 2000))->not->toContain('roomCost_');
});

test('a failed labour submission does not change the payment method of the other two forms', function () {
    $room = Room::factory()->for($this->customer)->create();

    // The room page renders payment_method three times (labour, extra
    // expense, customer payment). old() is global, so without a guard this
    // one choice would preselect itself in all three.
    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.costs.store', $room), [
            'type' => 'labor',
            'amount' => '', // fails
            'occurred_at' => '2026-01-05',
            'payment_method' => 'instapay',
        ])
        ->getContent();

    // Exactly one of the three selects has instapay selected — the one that
    // was submitted; the others fall back to the cash default.
    expect(substr_count($html, 'value="instapay" selected'))->toBe(1);
    expect(substr_count($html, 'value="cash" selected'))->toBe(2);

    $labor = substr($html, strpos($html, 'roomCost_labor_payment_method'), 900);
    expect($labor)->toContain('value="instapay" selected');
});

test('a failed customer payment does not change the payment method of the cost forms', function () {
    $room = Room::factory()->for($this->customer)->create(['sale_price' => 100_000]);

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->post(route('rooms.payments.store', $room), [
            'amount' => '99999.00', // more than the sale price
            'paid_at' => '2026-01-05',
            'payment_method' => 'cheque',
        ])
        ->getContent();

    expect(substr_count($html, 'value="cheque" selected'))->toBe(1);
    expect(substr($html, strpos($html, 'payment_payment_method'), 900))->toContain('value="cheque" selected');
});

test('the room page defaults every payment method select to cash', function () {
    $room = Room::factory()->for($this->customer)->create();

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->getContent();

    expect(substr_count($html, 'value="cash" selected'))->toBe(3);
});

test('a room cost records the chosen payment method on its cashbox row', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'labor',
        'amount' => '5000.00',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'wallet',
    ]);

    $transaction = CashboxTransaction::query()->where('source_type', RoomCost::class)->sole();
    expect($transaction->payment_method)->toBe(PaymentMethod::Wallet);
});

test('an invalid payment method is rejected in the right bag', function () {
    $room = Room::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->post(route('rooms.costs.store', $room), [
        'type' => 'other',
        'amount' => '100.00',
        'occurred_at' => '2026-01-05',
        'payment_method' => 'bitcoin',
    ])->assertSessionHasErrors(['payment_method'], null, 'roomCost_other');

    expect(RoomCost::query()->count())->toBe(0);
});
