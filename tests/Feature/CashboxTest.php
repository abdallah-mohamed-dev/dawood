<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\PaymentMethod;
use App\Models\CashboxTransaction;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->cashbox = app(CashboxService::class);
    $this->source = User::factory()->create();
});

test('the cashbox page puts a month separator row in each table for each month with movements', function () {
    $this->cashbox->recordIn($this->source, 1_234, CashboxTransactionKind::CustomerPayment, '2026-06-10');
    $this->cashbox->recordIn($this->source, 999, CashboxTransactionKind::CustomerPayment, '2026-07-02');
    $this->cashbox->recordOut($this->source, 300, CashboxTransactionKind::Expense, '2026-06-15');

    $content = $this->actingAs($this->admin)->get(route('cashbox.index'))->assertOk()->getContent();

    // Two separators in the incoming table (June, July) and one in the outgoing table (June).
    expect(substr_count($content, 'إجمالي الشهر'))->toBe(3)
        ->and($content)->toContain(__('date.months.6').' 2026')
        ->and($content)->toContain(__('date.months.7').' 2026');
});

test('each month separator shows the total for that month, checked by hand', function () {
    // June incoming: 1,234 + 567 = 1,801 EGP. July incoming: 999 EGP.
    $this->cashbox->recordIn($this->source, 1_234, CashboxTransactionKind::CustomerPayment, '2026-06-10');
    $this->cashbox->recordIn($this->source, 567, CashboxTransactionKind::CustomerPayment, '2026-06-20');
    $this->cashbox->recordIn($this->source, 999, CashboxTransactionKind::CustomerPayment, '2026-07-02');

    $this->actingAs($this->admin)
        ->get(route('cashbox.index'))
        ->assertSeeText('1,801 ج.م')
        ->assertSeeText('999 ج.م');
});

test('the month totals come from every row of that month, not only the rows on this page', function () {
    // 30 incoming rows of 2 EGP in June (total 60) plus one July row, so the
    // page total (110) differs from June's. Page 1 shows only about 25 June
    // rows (50), so the separator must come from the query to read 60.
    for ($i = 1; $i <= 30; $i++) {
        $this->cashbox->recordIn($this->source, 2, CashboxTransactionKind::CustomerPayment, '2026-06-'.str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT));
    }
    $this->cashbox->recordIn($this->source, 50, CashboxTransactionKind::CustomerPayment, '2026-07-01');

    $this->actingAs($this->admin)
        ->get(route('cashbox.index'))
        ->assertSeeText('60 ج.م');
});

test('the opening balance is still recorded with the chosen payment method', function () {
    $this->actingAs($this->admin)
        ->post(route('cashbox.opening-balance.store'), [
            'amount' => '5000',
            'occurred_at' => '2026-01-01',
            'payment_method' => 'wallet',
        ])
        ->assertRedirect(route('cashbox.index'));

    $opening = CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->sole();

    expect($opening->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($opening->getRawOriginal('amount'))->toBe(5_000);
});

test('the payment method field stays in the page so it is still submitted while hidden', function () {
    $this->actingAs($this->admin)
        ->get(route('cashbox.index'))
        ->assertSee('name="payment_method"', false)
        ->assertSee("persistedToggle('cashbox.opening.paymentMethod.visible')", false)
        ->assertSee("persistedToggle('cashbox.breakdown.visible')", false);
});

test('the balance and totals are the same before and after viewing the page', function () {
    $this->cashbox->setOpeningBalance(5_000, '2026-01-01');
    $this->cashbox->recordIn($this->source, 1_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');
    $this->cashbox->recordOut($this->source, 300, CashboxTransactionKind::Expense, '2026-01-03');

    $balanceBefore = $this->cashbox->balance();
    $inBefore = $this->cashbox->totalIn();
    $outBefore = $this->cashbox->totalOut();

    $this->actingAs($this->admin)->get(route('cashbox.index'))->assertOk();

    expect($this->cashbox->balance())->toBe($balanceBefore)->toBe(5_700)
        ->and($this->cashbox->totalIn())->toBe($inBefore)->toBe(6_000)
        ->and($this->cashbox->totalOut())->toBe($outBefore)->toBe(300);
});
