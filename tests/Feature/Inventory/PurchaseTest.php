<?php

use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access purchases', function () {
    $this->get(route('inventory.purchases.index'))->assertRedirect(route('login'));
});

test('recording a purchase creates a batch and reduces the cashbox balance', function () {
    $material = Material::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertRedirect(route('inventory.purchases.index'));

    $batch = InventoryBatch::query()->where('material_id', $material->id)->sole();
    expect($batch->getRawOriginal('quantity'))->toBe(3_000);
    expect($batch->getRawOriginal('unit_cost'))->toBe(10_000);
    expect(app(CashboxService::class)->balance())->toBe(-30_000);
});

test('the purchases index shows the material name, quantity, remaining, and unit cost', function () {
    $material = Material::factory()->create(['name' => 'لوح MDF']);

    $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->admin)->get(route('inventory.purchases.index'));

    $response->assertOk()
        ->assertSee('لوح MDF')
        ->assertSee('100.00 ج.م');
});

test('creating a purchase without a material fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => '',
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('material_id');
});

test('a zero unit cost is attributed to the unit_cost field, not quantity', function () {
    $material = Material::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '0',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('unit_cost');
    $response->assertSessionDoesntHaveErrors('quantity');
    expect(InventoryBatch::query()->count())->toBe(0);
});

test('a zero quantity is attributed to the quantity field, not unit_cost', function () {
    $material = Material::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '0',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('quantity');
    $response->assertSessionDoesntHaveErrors('unit_cost');
});

test('creating a purchase with scientific-notation quantity is rejected as a clean validation error', function () {
    $material = Material::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '1e10',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(InventoryBatch::query()->count())->toBe(0);
});

test('a purchase whose cost rounds to zero is rejected with a friendly message, not a 500', function () {
    $material = Material::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '0.001',
        'unit_cost' => '0.01',
        'purchase_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(InventoryBatch::query()->count())->toBe(0);
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('an untouched purchase can be deleted, restoring the cashbox balance', function () {
    $material = Material::factory()->create();
    $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);
    $batch = InventoryBatch::query()->sole();

    $response = $this->actingAs($this->admin)->delete(route('inventory.purchases.destroy', $batch));

    $response->assertRedirect(route('inventory.purchases.index'));
    expect(InventoryBatch::query()->find($batch->id))->toBeNull();
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('the materials index shows current stock after a purchase', function () {
    $material = Material::factory()->create(['name' => 'لوح MDF']);
    $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index'));

    $response->assertOk()->assertSee('3.000 '.$material->unit);
});

test('a material with a purchase batch cannot be deleted', function () {
    $material = Material::factory()->create();
    $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '3',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->admin)->delete(route('inventory.materials.destroy', $material));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Material::query()->find($material->id))->not->toBeNull();
});
