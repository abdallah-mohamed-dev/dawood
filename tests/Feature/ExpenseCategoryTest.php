<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access expense categories', function () {
    $this->get(route('expenses.categories.index'))->assertRedirect(route('login'));
});

test('a category can be created', function () {
    $response = $this->actingAs($this->admin)->post(route('expenses.categories.store'), [
        'name' => 'كهرباء',
    ]);

    $response->assertRedirect(route('expenses.categories.index'));
    expect(ExpenseCategory::query()->where('name', 'كهرباء')->exists())->toBeTrue();
});

test('two expense categories cannot share the same name', function () {
    ExpenseCategory::factory()->create(['name' => 'كهرباء']);

    $response = $this->actingAs($this->admin)->post(route('expenses.categories.store'), [
        'name' => 'كهرباء',
    ]);

    $response->assertSessionHasErrors('name');
});

test('a category with no expenses can be deleted', function () {
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($this->admin)->delete(route('expenses.categories.destroy', $category))
        ->assertRedirect(route('expenses.categories.index'));

    expect(ExpenseCategory::query()->find($category->id))->toBeNull();
});

test('deleting a category used by an expense is rejected', function () {
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->for($category, 'category')->create();

    $response = $this->actingAs($this->admin)->delete(route('expenses.categories.destroy', $category));

    $response->assertSessionHas('error');
    expect(ExpenseCategory::query()->find($category->id))->not->toBeNull();
});
