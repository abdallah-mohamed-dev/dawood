<?php

use App\Models\Material;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access materials', function () {
    $this->get(route('inventory.materials.index'))->assertRedirect(route('login'));
});

test('adding a material shows it listed with its unit', function () {
    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ])->assertRedirect(route('inventory.materials.index'));

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index'));

    $response->assertOk()
        ->assertSee('لوح MDF')
        ->assertSee('لوح');
});

test('creating a material without a name fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => '',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->count())->toBe(0);
});

test('creating a material without a unit fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => '',
    ]);

    $response->assertSessionHasErrors('unit');
    expect(Material::query()->count())->toBe(0);
});

test('two materials cannot share the same name anywhere in the system', function () {
    Material::factory()->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->where('name', 'لوح MDF')->count())->toBe(1);
});

test('a material can be edited', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'اسم محدث',
        'unit' => 'متر',
    ])->assertRedirect(route('inventory.materials.index'));

    $material->refresh();
    expect($material->name)->toBe('اسم محدث');
    expect($material->unit)->toBe('متر');
});

test('updating a material to its own current name does not fail uniqueness validation', function () {
    $material = Material::factory()->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'لوح MDF',
        'unit' => $material->unit,
    ]);

    $response->assertSessionDoesntHaveErrors();
});

test('renaming a material to another existing material name fails validation', function () {
    Material::factory()->create(['name' => 'لوح MDF']);
    $material = Material::factory()->create(['name' => 'خشب زان']);

    $response = $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'name' => 'لوح MDF',
        'unit' => $material->unit,
    ]);

    $response->assertSessionHasErrors('name');
    expect($material->fresh()->name)->toBe('خشب زان');
});

test('a material can be deleted', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->delete(route('inventory.materials.destroy', $material))
        ->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->find($material->id))->toBeNull();
});
