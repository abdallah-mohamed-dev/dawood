<?php

use App\Models\CapitalItem;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * specs/024 replaced every separate edit page (and the partner create page)
 * with an edit row under each table row, or a dialog on a detail page. These
 * guard what can quietly rot: a page route coming back, a row losing its
 * form, a failed save landing in the wrong form, and a save throwing the user
 * onto another page.
 */
test('no separate edit or create page survives', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'customers.edit',
    'capital.edit',
    'debts.edit',
    'expenses.edit',
    'expenses.categories.edit',
    'partners.create',
    'partners.edit',
    'payments.edit',
]);

test('each list renders an inline edit form for its rows', function (string $page, Closure $make, string $updateRoute) {
    $model = $make();

    $this->actingAs($this->admin)->get(route($page))
        ->assertOk()
        ->assertSee(route($updateRoute, $model))
        ->assertSee('name="editing_id" value="'.$model->id.'"', escape: false);
})->with([
    'customers' => ['customers.index', fn () => Customer::factory()->create(), 'customers.update'],
    'capital' => ['capital.index', fn () => CapitalItem::factory()->create(), 'capital.update'],
    'debts' => ['debts.index', fn () => Debt::factory()->create(), 'debts.update'],
    'categories' => ['expenses.categories.index', fn () => ExpenseCategory::factory()->create(), 'expenses.categories.update'],
    'partners' => ['partners.index', fn () => Partner::factory()->create(), 'partners.update'],
]);

test('a failed row save comes back with that row open and the add form left clean', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد', 'phone' => '0100']);

    $html = $this->actingAs($this->admin)
        ->from(route('customers.index'))
        ->followingRedirects()
        ->put(route('customers.update', $customer), [
            'editing_id' => $customer->id,
            'name' => '',
            'phone' => '0199',
        ])
        ->assertOk()
        ->getContent();

    // The row reopens (Alpine seeded with its id) and shows what was typed.
    expect($html)->toContain('editing: '.$customer->id);
    expect($html)->toContain('id="phone_'.$customer->id.'"');
    expect(substr_count($html, 'value="0199"'))->toBe(1);
    // The quick-add phone field above the table did not pick it up.
    expect($html)->toMatch('/id="phone"[^>]*value=""/s');
    expect($customer->fresh()->name)->toBe('أحمد');
});

test('a failed row save reports its error in the row bag only', function () {
    $item = CapitalItem::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('capital.index'))
        ->put(route('capital.update', $item), [
            'editing_id' => $item->id,
            'name' => 'بند',
            'amount' => '',
            'occurred_at' => '2026-01-01',
        ])
        ->assertRedirect(route('capital.index'))
        ->assertSessionHasErrorsIn('row', 'amount')
        ->assertSessionDoesntHaveErrors('amount');
});

test('saving a row returns to the same page with its filters', function () {
    $category = ExpenseCategory::factory()->create();
    $this->actingAs($this->admin)->post(route('expenses.store'), [
        'expense_category_id' => $category->id,
        'amount' => '100',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $expense = Expense::query()->sole();
    $page = route('expenses.index', ['expense_category_id' => $category->id, 'page' => 1]);

    $this->actingAs($this->admin)
        ->from($page)
        ->put(route('expenses.update', $expense), ['editing_id' => $expense->id, 'amount' => '250', 'payment_method' => 'wallet'])
        ->assertRedirect($page);

    expect($expense->fresh()->getRawOriginal('amount'))->toBe(250);
    expect($expense->fresh()->cashboxTransaction->payment_method->value)->toBe('wallet');
});

test('a payment edited from the payments page stays on the payments page', function () {
    $room = Room::factory()->create(['sale_price' => 10_000]);
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $room), [
        'amount' => '1000',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $this->actingAs($this->admin)
        ->from(route('payments.index'))
        ->put(route('payments.update', $payment), ['editing_id' => $payment->id, 'amount' => '1500', 'payment_method' => 'cash'])
        ->assertRedirect(route('payments.index'));

    expect($payment->fresh()->getRawOriginal('amount'))->toBe(1_500);
});

test('a failed payment row edit on the room page does not refill or open the add-payment form', function () {
    $room = Room::factory()->create(['sale_price' => 10_000]);
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $room), [
        'amount' => '1000',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $html = $this->actingAs($this->admin)
        ->from(route('rooms.show', $room))
        ->followingRedirects()
        ->put(route('payments.update', $payment), ['editing_id' => $payment->id, 'amount' => '99999', 'payment_method' => 'cash'])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('المبلغ أكبر من المتبقي');
    expect($html)->toContain('formOpen: false');
    expect($html)->toMatch('/id="amount"[^>]*value=""/s');
    expect(substr_count($html, 'value="99999"'))->toBe(1);
});

test('the customer and partner pages edit in a dialog that reopens on a failed save', function (Closure $make, string $show, string $update, string $dialog, array $payload) {
    $model = $make();

    $this->actingAs($this->admin)->get(route($show, $model))
        ->assertOk()
        ->assertSee('id="'.$dialog.'"', escape: false)
        ->assertDontSee("getElementById('{$dialog}')?.showModal()", escape: false);

    $this->actingAs($this->admin)
        ->from(route($show, $model))
        ->followingRedirects()
        ->put(route($update, $model), $payload)
        ->assertOk()
        ->assertSee("getElementById('{$dialog}')?.showModal()", escape: false);
})->with([
    'customer' => [fn () => Customer::factory()->create(), 'customers.show', 'customers.update', 'edit-customer-dialog', ['name' => '']],
    'partner' => [fn () => Partner::factory()->create(), 'partners.show', 'partners.update', 'edit-partner-dialog', ['name' => '', 'percentage' => '10']],
]);
