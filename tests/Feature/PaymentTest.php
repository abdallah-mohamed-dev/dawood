<?php

use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->room = Room::factory()->for($this->customer)->create(['sale_price' => 3_000_000]);
});

test('guests cannot list payments', function () {
    $this->get(route('payments.index'))->assertRedirect(route('login'));
});

test('recording a payment updates paid/remaining and the cashbox', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect();
    expect($this->room->fresh()->paidAmount())->toBe(1_000_000);
    expect($this->room->fresh()->remainingAmount())->toBe(2_000_000);
    expect(app(CashboxService::class)->balance())->toBe(1_000_000);
});

test('a payment exceeding the remaining amount shows the exact remaining figure', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '25000.00',
        'paid_at' => '2026-01-02',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(session('errors')->get('amount')[0])->toContain('20,000.00');
    expect($this->room->fresh()->paidAmount())->toBe(1_000_000);
});

test('scientific-notation payment amounts are rejected as a clean validation error', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '1e10',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(CustomerPayment::query()->count())->toBe(0);
});

test('a payment with an unsafely large magnitude is rejected as a clean validation error, not a 500', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '9999999999999999.00', // 16 digits — over ScaledIntegerCast's safe limit
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(CustomerPayment::query()->count())->toBe(0);
});

test('editing a payment to an unsafely large magnitude is rejected as a clean validation error, not a 500', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $response = $this->actingAs($this->admin)->put(route('payments.update', $payment), [
        'amount' => '9999999999999999.00',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect($payment->fresh()->getRawOriginal('amount'))->toBe(1_000_000);
});

test('a zero payment amount is rejected', function () {
    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '0',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('deleting a payment restores the cashbox and shows no orphaned transaction', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $response = $this->actingAs($this->admin)->delete(route('payments.destroy', $payment));

    $response->assertRedirect(route('rooms.show', $this->room));
    expect($this->room->fresh()->paidAmount())->toBe(0);
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('a payment amount can be edited, updating the same cashbox transaction', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $response = $this->actingAs($this->admin)->put(route('payments.update', $payment), [
        'amount' => '15000.00',
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('rooms.show', $this->room));
    expect($this->room->fresh()->paidAmount())->toBe(1_500_000);
    expect(app(CashboxService::class)->balance())->toBe(1_500_000);
});

test('editing a payment beyond the remaining amount is rejected', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);
    $payment = CustomerPayment::query()->sole();

    $response = $this->actingAs($this->admin)->put(route('payments.update', $payment), [
        'amount' => '40000.00',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect($this->room->fresh()->paidAmount())->toBe(1_000_000);
});

test('the payments registry lists a payment with its customer and room', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'note' => 'دفعة مقدمة',
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->admin)->get(route('payments.index'));

    $response->assertOk()
        ->assertSee($this->customer->name)
        ->assertSee($this->room->room_type)
        ->assertSee('دفعة مقدمة')
        ->assertSee('10,000.00 ج.م');
});

test('a payment note of exactly "0" is preserved, not silently discarded', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'note' => '0',
        'payment_method' => 'cash',
    ]);

    expect(CustomerPayment::query()->sole()->note)->toBe('0');
});

test('a new payment for a cancelled room is rejected', function () {
    $this->room->update(['status' => RoomStatus::Cancelled]);

    $response = $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHas('error');
    expect(CustomerPayment::query()->count())->toBe(0);
});

test('deleting a room with payments also removes the payments and their cashbox transactions', function () {
    $this->actingAs($this->admin)->post(route('rooms.payments.store', $this->room), [
        'amount' => '10000.00',
        'paid_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $this->actingAs($this->admin)->delete(route('rooms.destroy', $this->room));

    expect(CustomerPayment::query()->count())->toBe(0);
    expect(app(CashboxService::class)->balance())->toBe(0);
});
