<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns creating, updating, and deleting administrative expenses — see
 * docs/expenses.md. Every expense is a cashbox outflow the moment it's
 * recorded; deleting/editing it reverses/updates that same transaction.
 */
class ExpenseService
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function create(ExpenseCategory $category, int $amount, DateTimeInterface|string $date, ?string $description = null): Expense
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        return DB::transaction(function () use ($category, $amount, $date, $description) {
            $expense = Expense::query()->create([
                'expense_category_id' => $category->id,
                'amount' => $amount,
                'occurred_at' => $date,
                'description' => $description,
            ]);

            $this->cashbox->recordOut($expense, $amount, CashboxTransactionKind::Expense, $date);

            return $expense;
        });
    }

    public function update(Expense $expense, int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        DB::transaction(function () use ($expense, $amount) {
            $expense = Expense::query()->whereKey($expense->getKey())->lockForUpdate()->firstOrFail();

            $expense->update(['amount' => $amount]);
            $this->cashbox->updateFor($expense, $amount);
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->cashbox->removeFor($expense);
            $expense->delete();
        });
    }
}
