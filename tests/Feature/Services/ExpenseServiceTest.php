<?php

use App\Models\CashboxTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\CashboxService;
use App\Services\ExpenseService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->expenses = new ExpenseService($this->cashbox);
    $this->category = ExpenseCategory::factory()->create(['name' => 'كهرباء']);
});

test('the reference scenario from docs/tasks.md produces exact expected numbers', function () {
    $expense = $this->expenses->create($this->category, 200_000, '2026-01-01'); // 2,000 EGP

    expect($expense->getRawOriginal('amount'))->toBe(200_000);
    expect($this->cashbox->balance())->toBe(-200_000);

    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->source_type)->toBe(Expense::class);
    expect($transaction->source_id)->toBe($expense->id);
});

test('deleting an expense restores the cashbox balance with no orphaned transaction', function () {
    $expense = $this->expenses->create($this->category, 200_000, '2026-01-01');

    $this->expenses->delete($expense);

    expect($this->cashbox->balance())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
    expect(Expense::query()->count())->toBe(0);
});

test('editing an expense amount updates the same cashbox transaction, not a new one', function () {
    $expense = $this->expenses->create($this->category, 200_000, '2026-01-01');

    $this->expenses->update($expense, 250_000);

    expect($this->cashbox->balance())->toBe(-250_000);
    expect(CashboxTransaction::query()->count())->toBe(1);
});

test('a zero or negative expense amount is rejected', function () {
    $this->expenses->create($this->category, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('updating an expense to a zero or negative amount is rejected', function () {
    $expense = $this->expenses->create($this->category, 200_000, '2026-01-01');

    $this->expenses->update($expense, -100);
})->throws(InvalidArgumentException::class);
