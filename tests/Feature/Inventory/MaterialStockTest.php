<?php

use App\Enums\InventoryMovementType;
use App\Models\CashboxTransaction;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;

/*
 * 14.4 — the stock page as the one place stock changes.
 *
 * The reference fixture, used by every arithmetic assertion below:
 *
 *   Material "خشب بلوط" — 10 units @ 100 EGP, bought on 2026-03-01.
 *   That single purchase costs 1,000 EGP, so the cashbox sits at -100,000
 *   piastres (a purchase pays out) before each test edits anything.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->material = Material::factory()->create([
        'name' => 'خشب بلوط',
        'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id'),
    ]);
    $this->inventory->addStock($this->material, 10, 100, '2026-03-01'); // 10 @ 100 → -100,000

    expect($this->cashbox->balance())->toBe(-1_000);
});

/**
 * The edit form's payload, with the three fields 14.4.7–14.4.9 added.
 *
 * @return array<string, mixed>
 */
function editMaterialPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'خشب بلوط',
        'unit' => 'لوح',
        'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id'),
        'unit_price' => '100',
        'quantity' => '10',
    ], $overrides);
}

test('guests cannot open the stock page', function () {
    $this->get(route('inventory.materials.index'))->assertRedirect(route('login'));
});

test('raising the quantity from the edit form takes the difference out of the cashbox', function () {
    // +2 units @ 100 EGP = 200 EGP out.
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['quantity' => '12']))
        ->assertRedirect(route('inventory.materials.index'));

    expect($this->inventory->currentStock($this->material))->toBe(12);
    expect($this->cashbox->balance())->toBe(-1_200); // -100,000 − 20,000

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::In)->latest('id')->first();
    expect($movement->getRawOriginal('quantity'))->toBe(2);
    expect($movement->getRawOriginal('cost'))->toBe(200);
    expect(CashboxTransaction::query()->count())->toBe(2);
});

test('lowering the quantity from the edit form puts the difference back in the cashbox', function () {
    // −3 units @ 100 EGP = 300 EGP back in.
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['quantity' => '7']))
        ->assertRedirect(route('inventory.materials.index'));

    expect($this->inventory->currentStock($this->material))->toBe(7);
    expect($this->cashbox->balance())->toBe(-700); // -100,000 + 30,000

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::Sold)->sole();
    expect($movement->getRawOriginal('quantity'))->toBe(3);
    expect($movement->getRawOriginal('cost'))->toBe(300);
});

test('changing the price and the quantity in one save prices the increase at the NEW price', function () {
    // The mandatory one (14.4.14): 10 @ 100 becomes 15 @ 200 in a single save.
    // The increase is 5 units, and it must be bought at 200 EGP → 1,000 EGP out,
    // not at the old 100 EGP (which would have been only 500).
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload([
            'unit_price' => '200',
            'quantity' => '15',
        ]))
        ->assertRedirect(route('inventory.materials.index'));

    $this->material->refresh();
    expect($this->material->getRawOriginal('quantity'))->toBe(15);
    expect($this->material->getRawOriginal('unit_price'))->toBe(200);

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::In)->latest('id')->first();
    expect($movement->getRawOriginal('quantity'))->toBe(5);
    expect($movement->getRawOriginal('cost'))->toBe(1_000); // 5 @ 200 EGP

    expect($this->cashbox->balance())->toBe(-2_000); // -100,000 − 100,000
});

test('changing only the price re-values the stock and moves no money at all', function () {
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload([
            'unit_price' => '250',
            'quantity' => '10',
        ]))
        ->assertRedirect(route('inventory.materials.index'));

    $this->material->refresh();
    expect($this->material->getRawOriginal('unit_price'))->toBe(250);
    expect($this->material->getRawOriginal('quantity'))->toBe(10);

    expect($this->inventory->stockValue())->toBe(2_500); // 10 @ 250 EGP
    expect($this->cashbox->balance())->toBe(-1_000);      // unchanged
    expect(CashboxTransaction::query()->count())->toBe(1);   // only the opening purchase
    expect(InventoryMovement::query()->count())->toBe(1);
});

test('a malformed price is rejected in Arabic and changes nothing', function (string $price, string $message) {
    $response = $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['unit_price' => $price]));

    $response->assertSessionHasErrors(['unit_price' => $message], null, 'materialRow');

    $this->material->refresh();
    expect($this->material->getRawOriginal('unit_price'))->toBe(100);
    expect($this->cashbox->balance())->toBe(-1_000);
})->with([
    'letters' => ['abc', 'قيمة سعر الوحدة غير صالحة.'],
    'too many decimals' => ['10.999', 'قيمة سعر الوحدة غير صالحة.'],
    'zero' => ['0', 'سعر الوحدة لازم يكون أكبر من صفر.'],
    'zero written with decimals' => ['0', 'سعر الوحدة لازم يكون أكبر من صفر.'],
    'negative' => ['-5', 'قيمة سعر الوحدة غير صالحة.'],
    'empty' => ['', 'حقل سعر الوحدة مطلوب.'],
]);

test('a malformed quantity is rejected in Arabic and changes nothing', function (string $quantity, string $message) {
    $response = $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['quantity' => $quantity]));

    $response->assertSessionHasErrors(['quantity' => $message], null, 'materialRow');

    expect($this->inventory->currentStock($this->material))->toBe(10);
    expect($this->cashbox->balance())->toBe(-1_000);
    expect(InventoryMovement::query()->count())->toBe(1);
})->with([
    'letters' => ['abc', 'قيمة الكمية غير صالحة.'],
    'too many decimals' => ['1.9999', 'قيمة الكمية غير صالحة.'],
    'negative' => ['-5', 'قيمة الكمية غير صالحة.'],
    'empty' => ['', 'حقل الكمية مطلوب.'],
]);

test('a negative quantity is refused and the stock never goes above zero', function () {
    $response = $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['quantity' => '-5']));

    $response->assertSessionHasErrors(['quantity' => 'قيمة الكمية غير صالحة.'], null, 'materialRow');

    expect($this->inventory->currentStock($this->material))->toBe(10);
});

test('zero quantity is allowed — it empties the material and pays the cashbox back', function () {
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload(['quantity' => '0']))
        ->assertRedirect(route('inventory.materials.index'));

    expect($this->inventory->currentStock($this->material))->toBe(0);
    expect($this->cashbox->balance())->toBe(0); // -100,000 + 100,000
});

test('a stock correction is recorded as cash without the row asking for a method', function () {
    // specs/023: the row has no payment-method control, and a save that sends
    // one anyway must not be able to talk the cashbox into another method.
    $this->actingAs($this->admin)
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload([
            'quantity' => '12',
            'payment_method' => 'wallet',
        ]))
        ->assertRedirect(route('inventory.materials.index'));

    expect(CashboxTransaction::query()->latest('id')->first()->payment_method->value)->toBe('cash');
});

test('a rejected inline save reopens only its own row, and leaves the quick-add form clean', function () {
    $other = Material::factory()->create(['name' => 'خشب زان']);

    // Nothing may read the session between the save and the page load: an
    // assertSessionHasErrors() in between consumes the flashed bag, and the
    // page would then render with no errors at all. The bag itself is already
    // asserted by the malformed-quantity tests above.
    $this->actingAs($this->admin)
        ->from(route('inventory.materials.index'))
        ->put(route('inventory.materials.update', $this->material), editMaterialPayload([
            'quantity' => '10.5',
            // The row posts its own id so a failed save knows which row to
            // reopen; old() alone is shared by the whole page.
            'editing_id' => $this->material->id,
        ]))
        ->assertRedirect(route('inventory.materials.index'));

    $html = $this->get(route('inventory.materials.index'))->assertOk()->getContent();

    // The failing row reopens; the other one stays shut.
    expect(substr_count($html, 'editing: true'))->toBe(1);
    expect(substr_count($html, 'editing: false'))->toBe(1);

    // The message belongs to the row, not to the quick-add field of the same
    // name sitting above the table.
    expect(substr_count($html, 'قيمة الكمية غير صالحة.'))->toBe(1);
    expect($this->inventory->currentStock($this->material))->toBe(10);
    expect($other->fresh()->getRawOriginal('quantity'))->toBe(0);
});

test('the stock page warns that a quantity change moves real money, and never asks how', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index'))
        ->assertOk()
        ->assertSee('بتخصم من الخزنة')
        ->assertSee('ما بيحرّكش فلوس')
        ->assertDontSee('طريقة الدفع');
});

test('the inline row is filled with the exact stored price and quantity', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('inventory.materials.index'))
        ->assertOk()
        ->getContent();

    // Whole units since specs/023: the field shows "10", with no decimal
    // point to re-save as a different number.
    expect($html)->toContain('name="unit_price"');
    expect($html)->toContain('value="100"');
    expect($html)->toContain('name="quantity"');
    expect($html)->toContain('value="10"');
    // The row posts straight to update() — there is no edit page behind it.
    expect($html)->toContain('id="material-edit-'.$this->material->id.'"');
});

test('quick-add opens a material with stock by buying it in the same request', function () {
    $typeId = MaterialType::query()->where('name', 'خامة')->value('id');

    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'خشب صنوبر',
        'unit' => 'لوح',
        'material_type_id' => $typeId,
        'unit_price' => '80',
        'quantity' => '4',
        'payment_method' => 'cash',
    ])->assertRedirect(route('inventory.materials.index'));

    $created = Material::query()->where('name', 'خشب صنوبر')->sole();
    // 4 @ 80 EGP = 320 EGP out, on top of the fixture's -100,000.
    expect($this->inventory->currentStock($created))->toBe(4);
    expect($created->getRawOriginal('unit_price'))->toBe(80);
    expect($this->cashbox->balance())->toBe(-1_320);

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::In)->latest('id')->first();
    expect($movement->getRawOriginal('cost'))->toBe(320);
});

test('quick-add catalogues a material with no stock and no payment method', function () {
    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'شمع تغليف',
        'unit' => 'قطعة',
        'material_type_id' => MaterialType::query()->where('name', 'اكسسوار')->value('id'),
        'unit_price' => '15',
    ])->assertRedirect(route('inventory.materials.index'));

    $created = Material::query()->where('name', 'شمع تغليف')->sole();
    expect($created->getRawOriginal('quantity'))->toBe(0);
    expect($created->getRawOriginal('unit_price'))->toBe(15);
    // No stock means no purchase, so the cashbox never moved.
    expect($this->cashbox->balance())->toBe(-1_000);
});

test('quick-add buys the opening stock as cash without asking for a method', function () {
    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'خشب صنوبر',
        'unit' => 'لوح',
        'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id'),
        'unit_price' => '80',
        'quantity' => '4',
    ])->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->where('name', 'خشب صنوبر')->exists())->toBeTrue();
    expect(CashboxTransaction::query()->latest('id')->first()->payment_method->value)->toBe('cash');
});

test('the summary cards show each type and a total that is their sum', function () {
    $accessory = Material::factory()->create([
        'name' => 'مسامير',
        'material_type_id' => MaterialType::query()->where('name', 'اكسسوار')->value('id'),
    ]);
    $this->inventory->addStock($accessory, 4, 50, '2026-03-01'); // 4 @ 50 EGP = 200 EGP

    $this->actingAs($this->admin)->get(route('inventory.materials.index'))
        ->assertOk()
        ->assertSee('قيمة خامة')
        ->assertSeeText('1,000 ج.م')
        ->assertSee('قيمة اكسسوار')
        ->assertSeeText('200 ج.م')
        ->assertSee('إجمالي قيمة المخزن')
        ->assertSeeText('1,200 ج.م');
});

test('the summary cards say in Arabic that they ignore the filters', function () {
    $this->actingAs($this->admin)->get(route('inventory.materials.index'))
        ->assertOk()
        ->assertSee('الكروت دي بتعرض قيمة المخزن كلها دايمًا');
});

test('the row value column is the quantity times the price, priced by the service', function () {
    // 10 @ 100 EGP = 1,000 EGP on the shelf, 100 EGP a unit.
    $this->actingAs($this->admin)->get(route('inventory.materials.index'))
        ->assertOk()
        ->assertSee('سعر الوحدة')
        ->assertSee('القيمة')
        ->assertSeeText('100 ج.م')
        ->assertSeeText('1,000 ج.م');
});

test('filtering by material type returns only that type', function () {
    $accessory = Material::factory()->create([
        'name' => 'مسامير',
        'material_type_id' => MaterialType::query()->where('name', 'اكسسوار')->value('id'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['material_type_id' => $accessory->material_type_id]))
        ->assertOk()
        ->assertSee('مسامير')
        ->assertDontSee('خشب بلوط');
});

test('the type filter and the search box work together', function () {
    $accessoryTypeId = MaterialType::query()->where('name', 'اكسسوار')->value('id');
    Material::factory()->create(['name' => 'مسامير صغيرة', 'material_type_id' => $accessoryTypeId]);
    Material::factory()->create(['name' => 'مسامير كبيرة', 'material_type_id' => $accessoryTypeId]);
    Material::factory()->create(['name' => 'خشب زان', 'material_type_id' => MaterialType::query()->where('name', 'خامة')->value('id')]);

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['material_type_id' => $accessoryTypeId, 'q' => 'صغيرة']))
        ->assertOk()
        ->assertSee('مسامير صغيرة')
        ->assertDontSee('مسامير كبيرة')
        ->assertDontSee('خشب بلوط');
});

test('the type filter survives moving to the second page', function () {
    $accessoryTypeId = MaterialType::query()->where('name', 'اكسسوار')->value('id');
    Material::factory()->count(60)->sequence(fn ($sequence) => [
        'name' => 'مسامير '.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT),
        'material_type_id' => $accessoryTypeId,
    ])->create();

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['material_type_id' => $accessoryTypeId]))
        ->assertOk()
        ->assertSee('material_type_id='.$accessoryTypeId, escape: false);

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['material_type_id' => $accessoryTypeId, 'page' => 2]))
        ->assertOk()
        ->assertSee('مسامير 060')
        ->assertDontSee('خشب بلوط');
});

test('the summary cards do not follow the type filter', function () {
    $accessory = Material::factory()->create([
        'name' => 'مسامير',
        'material_type_id' => MaterialType::query()->where('name', 'اكسسوار')->value('id'),
    ]);
    $this->inventory->addStock($accessory, 4, 50, '2026-03-01'); // +200 EGP

    // Filtering down to the accessories must not shrink the books.
    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index', ['material_type_id' => $accessory->material_type_id]))
        ->assertOk()
        ->assertSee('قيمة خامة')
        ->assertSeeText('1,000 ج.م')
        ->assertSee('إجمالي قيمة المخزن')
        ->assertSeeText('1,200 ج.م');
});

test('a material still holding stock cannot be deleted', function () {
    $this->actingAs($this->admin)
        ->delete(route('inventory.materials.destroy', $this->material))
        ->assertSessionHas('error');

    expect(Material::query()->find($this->material->id))->not->toBeNull();
});

test('a material already issued to a room cannot be deleted', function () {
    $inventory = $this->inventory;
    $roomMaterial = (new RoomMaterialService($inventory))->addRequirement(Room::factory()->create(), $this->material, 5_000);
    (new RoomMaterialService($inventory))->issue($roomMaterial, 2, '2026-03-02');

    // The box is empty again after giving everything back, and the row is gone
    // with it — but the material still has a paper trail.
    expect(RoomMaterial::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->delete(route('inventory.materials.destroy', $this->material))
        ->assertSessionHas('error');

    expect(Material::query()->find($this->material->id))->not->toBeNull();
});

test('an unused material with no stock can still be deleted', function () {
    $spare = Material::factory()->create(['name' => 'خشب زائد']);

    $this->actingAs($this->admin)
        ->delete(route('inventory.materials.destroy', $spare))
        ->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->find($spare->id))->toBeNull();
});
