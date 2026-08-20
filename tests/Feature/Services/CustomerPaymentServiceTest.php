<?php

use App\Enums\RoomStatus;
use App\Exceptions\PaymentExceedsRemainingException;
use App\Exceptions\RoomCancelledException;
use App\Models\CashboxTransaction;
use App\Models\CustomerPayment;
use App\Models\Room;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->payments = new CustomerPaymentService($this->cashbox);
    $this->room = Room::factory()->create(['sale_price' => 3_000_000]); // 30,000 EGP
});

test('the full reference scenario from docs/tasks.md produces exact expected numbers', function () {
    $payment = $this->payments->create($this->room, 1_000_000, '2026-01-01'); // 10,000 EGP

    expect($this->room->paidAmount())->toBe(1_000_000);
    expect($this->room->remainingAmount())->toBe(2_000_000);
    expect($this->cashbox->balance())->toBe(1_000_000);

    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->source_type)->toBe(CustomerPayment::class);
    expect($transaction->source_id)->toBe($payment->id);
});

test('a payment that would exceed the sale price is rejected with the exact remaining amount', function () {
    $this->payments->create($this->room, 1_000_000, '2026-01-01'); // paid 10,000, remaining 20,000

    try {
        $this->payments->create($this->room, 2_500_000, '2026-01-02'); // would total 35,000
        $this->fail('Expected PaymentExceedsRemainingException to be thrown.');
    } catch (PaymentExceedsRemainingException $e) {
        expect($e->remaining)->toBe(2_000_000);
    }

    expect($this->room->paidAmount())->toBe(1_000_000);
});

test('a payment exactly equal to the remaining amount brings remaining to zero', function () {
    $this->payments->create($this->room, 1_000_000, '2026-01-01');
    $this->payments->create($this->room, 2_000_000, '2026-01-02');

    expect($this->room->remainingAmount())->toBe(0);
});

test('deleting a payment restores the cashbox balance and leaves no orphaned transaction', function () {
    $payment = $this->payments->create($this->room, 1_000_000, '2026-01-01');

    $this->payments->delete($payment);

    expect($this->room->paidAmount())->toBe(0);
    expect($this->cashbox->balance())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
});

test('a zero or negative payment is rejected', function () {
    $this->payments->create($this->room, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('updating a payment amount updates the same cashbox transaction, not a new one', function () {
    $payment = $this->payments->create($this->room, 1_000_000, '2026-01-01');

    $this->payments->update($payment, 1_500_000);

    expect($this->room->paidAmount())->toBe(1_500_000);
    expect($this->cashbox->balance())->toBe(1_500_000);
    expect(CashboxTransaction::query()->count())->toBe(1);
});

test('updating a payment to exceed the remaining amount is rejected', function () {
    $paymentA = $this->payments->create($this->room, 1_000_000, '2026-01-01');
    $this->payments->create($this->room, 2_000_000, '2026-01-02'); // remaining now 0

    expect(fn () => $this->payments->update($paymentA, 1_500_000))
        ->toThrow(PaymentExceedsRemainingException::class);
});

test('a new payment for a cancelled room is rejected', function () {
    $this->room->update(['status' => RoomStatus::Cancelled]);

    expect(fn () => $this->payments->create($this->room, 1_000_000, '2026-01-01'))
        ->toThrow(RoomCancelledException::class);

    expect(CustomerPayment::query()->count())->toBe(0);
});
