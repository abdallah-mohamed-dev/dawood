<?php

use App\Models\Category;
use App\Models\Material;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access materials', function () {
    $this->get(route('inventory.materials.index'))->assertRedirect(route('login'));
});

test('creating a material without a name fails validation', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'category_id' => $category->id,
        'name' => '',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->count())->toBe(0);
});

test('creating a material without a category fails validation', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'category_id' => '',
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('category_id');
});

test('two materials cannot share the same name within the same category', function () {
    $category = Category::factory()->create();
    Material::factory()->for($category)->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'category_id' => $category->id,
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Material::query()->where('category_id', $category->id)->count())->toBe(1);
});

test('the same material name is allowed in two different categories', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    Material::factory()->for($categoryA)->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'category_id' => $categoryB->id,
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(Material::query()->where('name', 'لوح MDF')->count())->toBe(2);
});

test('a material can be edited, including moving it to a different category', function () {
    $originalCategory = Category::factory()->create();
    $newCategory = Category::factory()->create();
    $material = Material::factory()->for($originalCategory)->create();

    $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'category_id' => $newCategory->id,
        'name' => 'اسم محدث',
        'unit' => 'متر',
    ])->assertRedirect(route('inventory.materials.index'));

    $material->refresh();
    expect($material->category_id)->toBe($newCategory->id);
    expect($material->name)->toBe('اسم محدث');
    expect($material->unit)->toBe('متر');
});

test('updating a material to its own current name does not fail uniqueness validation', function () {
    $category = Category::factory()->create();
    $material = Material::factory()->for($category)->create(['name' => 'لوح MDF']);

    $response = $this->actingAs($this->admin)->put(route('inventory.materials.update', $material), [
        'category_id' => $category->id,
        'name' => 'لوح MDF',
        'unit' => $material->unit,
    ]);

    $response->assertSessionDoesntHaveErrors();
});

test('a material can be deleted', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->delete(route('inventory.materials.destroy', $material))
        ->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->find($material->id))->toBeNull();
});
