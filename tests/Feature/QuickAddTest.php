<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * Task 7 replaced every separate create page with a form sitting on the
 * index itself. These guard the two things that can quietly rot: a create
 * route coming back to life beside the inline form (two paths to one
 * insert), and a failed submission losing what the user typed.
 */
test('no create route survives for the converted pages', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'inventory.materials.create',
    'customers.create',
    'expenses.categories.create',
    'inventory.purchases.create',
    'expenses.create',
    'rooms.create',
]);

test('the add form is rendered on the index page itself', function (string $route, string $title) {
    $this->actingAs($this->admin)->get(route($route))->assertOk()->assertSee($title);
})->with([
    ['inventory.materials.index', 'إضافة مادة'],
    ['customers.index', 'إضافة عميل'],
    ['expenses.categories.index', 'إضافة بند'],
    ['inventory.purchases.index', 'تسجيل عملية شراء'],
    ['expenses.index', 'تسجيل مصروف'],
]);

test('a material is added from the index without leaving it', function () {
    $this->actingAs($this->admin)->post(route('inventory.materials.store'), [
        'name' => 'خشب زان',
        'unit' => 'لوح',
    ])->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->sole()->name)->toBe('خشب زان');
});

test('a customer is added from the index', function () {
    $this->actingAs($this->admin)->post(route('customers.store'), [
        'name' => 'محمد أحمد',
        'phone' => '01012345678',
        'address' => 'المعادي',
    ]);

    expect(Customer::query()->sole()->name)->toBe('محمد أحمد');
});

test('an expense category is added from the index', function () {
    $this->actingAs($this->admin)->post(route('expenses.categories.store'), ['name' => 'كهرباء']);

    expect(ExpenseCategory::query()->sole()->name)->toBe('كهرباء');
});

test('a purchase is recorded from the index', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->admin)->post(route('inventory.purchases.store'), [
        'material_id' => $material->id,
        'quantity' => '10',
        'unit_cost' => '100.00',
        'purchase_date' => '2026-01-01',
        'payment_method' => 'cash',
    ])->assertRedirect(route('inventory.purchases.index'));
});

test('a failed submission comes back to the index with the values still filled in', function () {
    $this->actingAs($this->admin)
        ->from(route('inventory.materials.index'))
        ->followingRedirects()
        ->post(route('inventory.materials.store'), ['name' => 'خشب زان', 'unit' => ''])
        ->assertOk()
        ->assertSee('value="خشب زان"', escape: false)
        // The error belongs to the form above the table, on the same page.
        ->assertSee('إضافة مادة');

    expect(Material::query()->count())->toBe(0);
});

test('a failed expense keeps the chosen category, amount and date', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'كهرباء']);

    $html = $this->actingAs($this->admin)
        ->from(route('expenses.index'))
        ->followingRedirects()
        ->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'amount' => 'abc',
            'occurred_at' => '2026-03-15',
            'description' => 'فاتورة مارس',
            'payment_method' => 'wallet',
        ])
        ->getContent();

    expect($html)->toContain('value="2026-03-15"');
    expect($html)->toContain('value="فاتورة مارس"');
    expect($html)->toContain('value="'.$category->id.'" selected');
    expect($html)->toContain('value="wallet" selected');
    expect(Expense::query()->count())->toBe(0);
});

test('a failed room submission stays on the customer page with its values', function () {
    $customer = Customer::factory()->create();

    $html = $this->actingAs($this->admin)
        ->from(route('customers.show', $customer))
        ->followingRedirects()
        ->post(route('customers.rooms.store', $customer), [
            'room_type' => 'غرفة سفرة',
            'sale_price' => '',
        ])
        ->getContent();

    expect($html)->toContain('value="غرفة سفرة"');
    expect(Room::query()->count())->toBe(0);
});

test('each index page carries exactly one add form, so the default error bag stays safe', function (string $route) {
    $html = $this->actingAs($this->admin)->get(route($route))->getContent();

    // Counting POST forms: the quick-add one, plus any delete button in the
    // table. With no rows there should be exactly one.
    expect(substr_count($html, 'method="POST"'))->toBe(2); // quick-add + logout
})->with([
    'inventory.materials.index',
    'customers.index',
    'expenses.categories.index',
    'inventory.purchases.index',
    'expenses.index',
]);
