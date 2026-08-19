<?php

use App\Models\Category;
use App\Models\Material;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access categories', function () {
    $this->get(route('inventory.categories.index'))->assertRedirect(route('login'));
});

test('adding a category and a material shows the material listed under its category', function () {
    $this->actingAs($this->admin)->post(route('inventory.categories.store'), [
        'name' => 'أخشاب',
    ])->assertRedirect(route('inventory.categories.index'));

    $category = Category::query()->where('name', 'أخشاب')->firstOrFail();

    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'category_id' => $category->id,
        'name' => 'لوح MDF',
        'unit' => 'لوح',
    ])->assertRedirect(route('inventory.materials.index'));

    $response = $this->actingAs($this->admin)->get(route('inventory.materials.index'));

    $response->assertOk()
        ->assertSee('أخشاب')
        ->assertSee('لوح MDF')
        ->assertSee('لوح');
});

test('creating a category without a name fails validation in Arabic', function () {
    $response = $this->actingAs($this->admin)->post(route('inventory.categories.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
    expect(session('errors')->get('name')[0])->toBe('حقل الاسم مطلوب.');
});

test('two categories cannot share the same name', function () {
    Category::factory()->create(['name' => 'أخشاب']);

    $response = $this->actingAs($this->admin)->post(route('inventory.categories.store'), [
        'name' => 'أخشاب',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Category::query()->where('name', 'أخشاب')->count())->toBe(1);
});

test('a category can be edited', function () {
    $category = Category::factory()->create(['name' => 'أخشاب']);

    $this->actingAs($this->admin)->put(route('inventory.categories.update', $category), [
        'name' => 'أخشاب مستوردة',
    ])->assertRedirect(route('inventory.categories.index'));

    expect($category->fresh()->name)->toBe('أخشاب مستوردة');
});

test('updating a category to its own current name does not fail uniqueness validation', function () {
    $category = Category::factory()->create(['name' => 'أخشاب']);

    $response = $this->actingAs($this->admin)->put(route('inventory.categories.update', $category), [
        'name' => 'أخشاب',
    ]);

    $response->assertSessionDoesntHaveErrors('name');
});

test('a category with no materials can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->delete(route('inventory.categories.destroy', $category))
        ->assertRedirect(route('inventory.categories.index'));

    expect(Category::query()->find($category->id))->toBeNull();
});

test('deleting a category that has materials is rejected with a clear message', function () {
    $category = Category::factory()->create();
    Material::factory()->for($category)->create();

    $response = $this->actingAs($this->admin)->delete(route('inventory.categories.destroy', $category));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Category::query()->find($category->id))->not->toBeNull();
});
