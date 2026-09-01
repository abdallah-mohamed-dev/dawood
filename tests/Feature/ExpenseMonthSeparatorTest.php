<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->category = ExpenseCategory::factory()->create(['name' => 'كهرباء']);
});

/**
 * Only the results table. The quick-add form above it also contains user
 * input, so scoping to the table avoids a false match there (same pattern
 * as purchasesTable() in PurchaseFilterTest).
 */
function expensesTable(array $query = []): string
{
    $html = test()->actingAs(test()->admin)
        ->get(route('expenses.index', $query))
        ->assertOk()
        ->getContent();

    return substr($html, strpos($html, 'overflow-x-auto rounded-xl'));
}

test('a separator row appears once between two different months', function () {
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-15', 'amount' => 50_000]);
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-01', 'amount' => 100_000]);
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-08-20', 'amount' => 200_000]);

    $table = expensesTable();

    expect(substr_count($table, 'سبتمبر 2026'))->toBe(1);
    expect(substr_count($table, 'أغسطس 2026'))->toBe(1);
});

test('no separator appears between two rows in the same month', function () {
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-15', 'amount' => 50_000]);
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-01', 'amount' => 100_000]);

    $table = expensesTable();

    // Ordered latest-first, both rows are September — exactly one heading,
    // not one per row.
    expect(substr_count($table, 'سبتمبر 2026'))->toBe(1);
});

test('the separator shows the correct total for its month', function () {
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-15', 'amount' => 100_000]); // 1,000.00
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-09-01', 'amount' => 50_000]);  // 500.00
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-08-20', 'amount' => 999_999]); // must not bleed into September's total

    $table = expensesTable();

    expect($table)->toContain('سبتمبر 2026');
    expect($table)->toContain('1,500.00 ج.م');
});

test('a month split across two pages shows its true total on each page, not a partial one', function () {
    // 30 expenses in the same month, one page holds 25.
    Expense::factory()->for($this->category, 'category')->count(30)->sequence(fn ($sequence) => [
        'occurred_at' => '2026-09-'.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT),
        'amount' => 10_000, // 100.00 EGP each — total 3,000.00
    ])->create();

    $pageOne = expensesTable();
    $pageTwo = expensesTable(['page' => 2]);

    expect($pageOne)->toContain('3,000.00 ج.م');
    expect($pageTwo)->toContain('3,000.00 ج.م');

    // Guard against the bug this exists to prevent: a total computed from
    // only the visible rows would show 2,500.00 on page one and 500.00 on
    // page two instead of the true 3,000.00 on both.
    expect($pageOne)->not->toContain('2,500.00');
    expect($pageTwo)->not->toContain('500.00 ج.م');
});

test('an expense with no month siblings still gets its own separator with its own total', function () {
    Expense::factory()->for($this->category, 'category')->create(['occurred_at' => '2026-05-10', 'amount' => 75_000]);

    $table = expensesTable();

    expect($table)->toContain('مايو 2026');
    expect(substr_count($table, '750.00 ج.م'))->toBe(2); // the separator total and the single row's own amount happen to match
});
