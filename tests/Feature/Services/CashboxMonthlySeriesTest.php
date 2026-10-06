<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Models\CashboxTransaction;
use App\Services\CashboxService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Fixed "today" so the six-month window is the same on every run.
    Carbon::setTestNow('2026-10-06 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A cashbox row with no source — the cashbox only needs the amount and the date here. */
function movement(CashboxTransactionType $type, int $piastres, string $date): void
{
    CashboxTransaction::factory()->create([
        'type' => $type,
        'amount' => $piastres,
        'kind' => CashboxTransactionKind::Expense,
        'occurred_at' => $date,
        'source_type' => null,
        'source_id' => null,
    ]);
}

test('the series ends on the same balance the cashbox reports', function () {
    // Opening balance 77,000 EGP, dated before the six-month window.
    app(CashboxService::class)->setOpeningBalance(7700000, '2026-01-01');

    // Six months (May → October): 300,000 in and 192,750 out in total.
    movement(CashboxTransactionType::In, 10000000, '2026-05-10');
    movement(CashboxTransactionType::In, 5000000, '2026-06-10');
    movement(CashboxTransactionType::Out, 9275000, '2026-07-10');
    movement(CashboxTransactionType::In, 15000000, '2026-08-10');
    movement(CashboxTransactionType::Out, 10000000, '2026-10-02');

    $series = app(CashboxService::class)->monthlySeries(6);

    expect($series)->toHaveCount(6);
    expect(array_column($series, 'balance_end'))->toBe([
        17700000, 22700000, 13425000, 28425000, 28425000, 18425000,
    ]);
    expect(end($series)['balance_end'])->toBe(app(CashboxService::class)->balance());
    expect(end($series)['balance_end'])->toBe(18425000);
});

test('a month with no movements keeps the balance of the month before it', function () {
    movement(CashboxTransactionType::In, 10000000, '2026-05-10');

    $september = collect(app(CashboxService::class)->monthlySeries(6))->firstWhere('month', '2026-09');

    expect($september['in'])->toBe(0);
    expect($september['out'])->toBe(0);
    expect($september['balance_end'])->toBe(10000000);
});

test('movements older than the window are carried into the first month', function () {
    movement(CashboxTransactionType::In, 50000000, '2025-01-15');
    movement(CashboxTransactionType::Out, 20000000, '2025-12-01');
    movement(CashboxTransactionType::In, 1000000, '2026-05-02');

    $series = app(CashboxService::class)->monthlySeries(6);

    // 500,000 − 200,000 carried from 2025, then +10,000 in May.
    expect($series[0]['balance_end'])->toBe(31000000);
    expect($series[0]['in'])->toBe(1000000);
});

test('the series reads the database in at most two queries', function () {
    for ($i = 0; $i < 100; $i++) {
        movement(CashboxTransactionType::In, 1000, '2026-0'.(($i % 9) + 1).'-10');
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(CashboxService::class)->monthlySeries(6);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(2);
});

test('every figure in the series is an integer in piastres', function () {
    movement(CashboxTransactionType::In, 1234, '2026-08-10');

    foreach (app(CashboxService::class)->monthlySeries(6) as $row) {
        expect($row['in'])->toBeInt();
        expect($row['out'])->toBeInt();
        expect($row['balance_end'])->toBeInt();
    }
});

test('the series labels months in Arabic from the language file', function () {
    $series = app(CashboxService::class)->monthlySeries(6);

    expect(end($series)['label'])->toBe('أكتوبر');
    expect($series[0]['label'])->toBe('مايو');
});
