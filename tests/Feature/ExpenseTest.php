<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->category = ExpenseCategory::factory()->create(['name' => 'كهرباء']);
});

test('guests cannot access expenses', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('recording an expense creates a cashbox outflow', function () {
    $response = $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'description' => 'فاتورة يناير',
        'payment_method' => 'cash',
    ]);

    // back() to the index, which is now also the add form's page.
    $response->assertRedirect();
    expect(app(CashboxService::class)->balance())->toBe(-200_000);
});

test('the expenses index lists an expense with its category, description, and amount', function () {
    $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'description' => 'فاتورة يناير',
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->admin)->get(route('expenses.index'));

    $response->assertOk()
        ->assertSee('كهرباء')
        ->assertSee('فاتورة يناير')
        ->assertSee('2,000.00 ج.م');
});

test('a zero expense amount is rejected', function () {
    $response = $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '0',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(Expense::query()->count())->toBe(0);
});

test('an expense description of exactly "0" is preserved, not silently discarded', function () {
    $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'description' => '0',
        'payment_method' => 'cash',
    ]);

    expect(Expense::query()->sole()->description)->toBe('0');
});

test('an unsafely large expense amount is rejected as a clean validation error, not a 500', function () {
    $response = $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '9999999999999999.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(Expense::query()->count())->toBe(0);
});

test('an expense amount can be edited, updating the same cashbox transaction', function () {
    $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $expense = Expense::query()->sole();

    $response = $this->actingAs($this->admin)->put(route('expenses.update', $expense), [
        'amount' => '2500.00',
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('expenses.index'));
    expect(app(CashboxService::class)->balance())->toBe(-250_000);
});

test('deleting an expense restores the cashbox balance', function () {
    $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $this->category->id,
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $expense = Expense::query()->sole();

    $response = $this->actingAs($this->admin)->delete(route('expenses.destroy', $expense));

    $response->assertRedirect(route('expenses.index'));
    expect(app(CashboxService::class)->balance())->toBe(0);
});
