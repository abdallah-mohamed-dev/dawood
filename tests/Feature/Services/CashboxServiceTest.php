<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Models\CashboxTransaction;
use App\Models\User;
use App\Services\CashboxService;

// CashboxService takes any Eloquent model as the polymorphic "source" of a
// transaction. Real source models (CustomerPayment, InventoryBatch, Expense,
// PartnerWithdrawal) don't exist yet — the cashbox is intentionally built
// before them (docs/tasks.md). User stands in as "any model" here; the
// relationship is purely polymorphic and does not care about the type.
beforeEach(function () {
    $this->cashbox = new CashboxService;
});

test('setting an opening balance creates a single transaction', function () {
    $this->cashbox->setOpeningBalance(500_000, '2026-01-01');

    expect($this->cashbox->balance())->toBe(500_000);
    expect(CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->count())->toBe(1);
});

test('setting the opening balance again updates the existing row instead of inserting a new one', function () {
    $this->cashbox->setOpeningBalance(500_000, '2026-01-01');
    $this->cashbox->setOpeningBalance(700_000, '2026-01-02');

    expect($this->cashbox->balance())->toBe(700_000);
    expect(CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->count())->toBe(1);
});

test('the opening balance cannot be negative', function () {
    $this->cashbox->setOpeningBalance(-1, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('recordIn and recordOut update the balance and totals correctly', function () {
    $source = User::factory()->create();

    $this->cashbox->recordIn($source, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-01');
    $this->cashbox->recordOut($source, 40_000, CashboxTransactionKind::Expense, '2026-01-02');

    expect($this->cashbox->balance())->toBe(60_000);
    expect($this->cashbox->totalIn())->toBe(100_000);
    expect($this->cashbox->totalOut())->toBe(40_000);
});

test('summary returns the same numbers as the individual total/balance methods, in one query', function () {
    $source = User::factory()->create();
    $this->cashbox->recordIn($source, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-01');
    $this->cashbox->recordOut($source, 40_000, CashboxTransactionKind::Expense, '2026-01-02');

    expect($this->cashbox->summary())->toBe([
        'total_in' => 100_000,
        'total_out' => 40_000,
        'balance' => 60_000,
    ]);
});

test('summary returns zeroes when there are no transactions at all', function () {
    expect($this->cashbox->summary())->toBe([
        'total_in' => 0,
        'total_out' => 0,
        'balance' => 0,
    ]);
});

test('recordIn links the transaction to its source via the polymorphic relation', function () {
    $source = User::factory()->create();

    $transaction = $this->cashbox->recordIn($source, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-01');

    expect($transaction->source_type)->toBe(User::class);
    expect($transaction->source_id)->toBe($source->id);
    expect($transaction->type)->toBe(CashboxTransactionType::In);
});

test('removeFor deletes the transaction and restores the prior balance', function () {
    $source = User::factory()->create();
    $this->cashbox->recordIn($source, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-01');

    expect($this->cashbox->balance())->toBe(100_000);

    $this->cashbox->removeFor($source);

    expect($this->cashbox->balance())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
});

test('updateFor changes the amount of the existing transaction without creating a new one', function () {
    $source = User::factory()->create();
    $this->cashbox->recordOut($source, 2_000, CashboxTransactionKind::Expense, '2026-01-01');

    $this->cashbox->updateFor($source, 2_500);

    expect($this->cashbox->balance())->toBe(-2_500);
    expect(CashboxTransaction::query()->count())->toBe(1);
});

test('updateFor fails loudly instead of silently no-oping when the source has no transaction', function () {
    $source = User::factory()->create();

    $this->cashbox->updateFor($source, 2_500);
})->throws(RuntimeException::class);

test('recordIn rejects a zero amount', function () {
    $source = User::factory()->create();

    $this->cashbox->recordIn($source, 0, CashboxTransactionKind::CustomerPayment, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('recordOut rejects a negative amount', function () {
    $source = User::factory()->create();

    $this->cashbox->recordOut($source, -100, CashboxTransactionKind::Expense, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('updateFor rejects a non-positive amount', function () {
    $source = User::factory()->create();
    $this->cashbox->recordOut($source, 2_000, CashboxTransactionKind::Expense, '2026-01-01');

    $this->cashbox->updateFor($source, 0);
})->throws(InvalidArgumentException::class);

test('the full reference scenario from docs/tasks.md produces the exact expected balance', function () {
    $source = User::factory()->create();

    $this->cashbox->setOpeningBalance(1_000_000, '2026-01-01');
    $this->cashbox->recordOut($source, 30_000, CashboxTransactionKind::InventoryPurchase, '2026-01-02');
    $this->cashbox->recordOut($source, 120_000, CashboxTransactionKind::InventoryPurchase, '2026-01-03');
    $this->cashbox->recordIn($source, 1_000_000, CashboxTransactionKind::CustomerPayment, '2026-01-04');
    $this->cashbox->recordOut($source, 200_000, CashboxTransactionKind::Expense, '2026-01-05');

    expect($this->cashbox->balance())->toBe(1_650_000);
});
