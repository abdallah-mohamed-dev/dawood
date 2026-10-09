<?php

use App\Enums\CashboxTransactionKind;
use App\Models\CashboxTransaction;
use App\Models\Debt;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->cashbox = app(CashboxService::class);
    $this->cashbox->setOpeningBalance(5_000, '2026-01-01');
});

function debtPayload(array $overrides = []): array
{
    return array_merge([
        'creditor' => 'مورد الخشب',
        'amount' => '1500',
        'incurred_at' => '2026-10-01',
        'due_at' => null,
        'note' => null,
    ], $overrides);
}

test('adding a debt shows it in the outstanding section', function () {
    $this->actingAs($this->admin)
        ->post(route('debts.store'), debtPayload(['creditor' => 'مورد الدهانات']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->get(route('debts.index'))
        ->assertSee('ديون قائمة')
        ->assertSee('مورد الدهانات');
});

test('adding a debt creates no cashbox movement and leaves the balance alone', function () {
    $balanceBefore = $this->cashbox->balance();
    $rowsBefore = CashboxTransaction::query()->count();

    $this->actingAs($this->admin)->post(route('debts.store'), debtPayload());

    expect($this->cashbox->balance())->toBe($balanceBefore)
        ->and(CashboxTransaction::query()->count())->toBe($rowsBefore);
});

test('marking a debt as settled creates no cashbox movement and leaves the balance alone', function () {
    $this->actingAs($this->admin)->post(route('debts.store'), debtPayload());
    $debt = Debt::query()->sole();

    $balanceBefore = $this->cashbox->balance();
    $rowsBefore = CashboxTransaction::query()->count();

    $this->actingAs($this->admin)->post(route('debts.toggle-paid', $debt));

    expect($this->cashbox->balance())->toBe($balanceBefore)
        ->and(CashboxTransaction::query()->count())->toBe($rowsBefore);
});

test('marking a debt as settled moves it to the settled section and sets paid_at', function () {
    $this->actingAs($this->admin)->post(route('debts.store'), debtPayload());
    $debt = Debt::query()->sole();

    $this->actingAs($this->admin)->post(route('debts.toggle-paid', $debt));

    $debt->refresh();
    expect($debt->is_paid)->toBeTrue()
        ->and($debt->paid_at->toDateString())->toBe(now()->toDateString());

    $this->actingAs($this->admin)
        ->get(route('debts.index'))
        ->assertSee('ديون مسدَّدة');
});

test('unmarking a settled debt returns it to outstanding and clears paid_at', function () {
    $this->actingAs($this->admin)->post(route('debts.store'), debtPayload());
    $debt = Debt::query()->sole();
    $this->actingAs($this->admin)->post(route('debts.toggle-paid', $debt));

    $this->actingAs($this->admin)->post(route('debts.toggle-paid', $debt));

    $debt->refresh();
    expect($debt->is_paid)->toBeFalse()
        ->and($debt->paid_at)->toBeNull();
});

test('a debt past its due date that is still outstanding is overdue and shows the red border', function () {
    $this->travelTo('2026-10-10');
    $debt = Debt::factory()->create(['due_at' => '2026-10-05', 'is_paid' => false]);

    expect($debt->isOverdue())->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('debts.index'))
        ->assertSee('border-danger');
});

test('a settled debt past its due date is not overdue', function () {
    $debt = Debt::factory()->create(['due_at' => '2026-10-05', 'is_paid' => true, 'paid_at' => '2026-10-06']);

    expect($debt->isOverdue())->toBeFalse();
});

test('a debt with no due date is not overdue', function () {
    $debt = Debt::factory()->create(['due_at' => null, 'is_paid' => false]);

    expect($debt->isOverdue())->toBeFalse();
});

test('a due date before the incurred date is rejected with an Arabic message', function () {
    $this->actingAs($this->admin)
        ->post(route('debts.store'), debtPayload(['incurred_at' => '2026-10-10', 'due_at' => '2026-10-01']))
        ->assertSessionHasErrors('due_at');

    expect(Debt::query()->count())->toBe(0);
});

test('a zero or negative amount is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('debts.store'), debtPayload(['amount' => '0']))
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->admin)
        ->post(route('debts.store'), debtPayload(['amount' => '-50']))
        ->assertSessionHasErrors('amount');

    expect(Debt::query()->count())->toBe(0);
});

test('the cashbox box shows only outstanding debts, checked against a hand-computed figure', function () {
    // Outstanding 1000.00, settled 500.00 → the box must show 1,000.00 and never 1,500.00.
    Debt::factory()->create(['amount' => 1_000, 'is_paid' => false]);
    Debt::factory()->create(['amount' => 500, 'is_paid' => true, 'paid_at' => '2026-10-02']);

    $this->actingAs($this->admin)
        ->get(route('cashbox.index'))
        ->assertSee('إجمالي الديون القائمة')
        ->assertSeeText('1,000 ج.م')
        ->assertDontSeeText('1,500 ج.م');
});

test('the cashbox box explains that debts are not part of the balance', function () {
    $this->actingAs($this->admin)
        ->get(route('cashbox.index'))
        ->assertSee('الديون للتسجيل والتذكير فقط ولا تدخل في رصيد الخزنة.');
});

test('the cashbox balance does not change through any debt operation', function () {
    $balanceBefore = $this->cashbox->balance();

    $this->actingAs($this->admin)->post(route('debts.store'), debtPayload());
    $debt = Debt::query()->sole();
    $this->actingAs($this->admin)->post(route('debts.toggle-paid', $debt));
    $this->actingAs($this->admin)->get(route('cashbox.index'))->assertOk();

    expect($this->cashbox->balance())->toBe($balanceBefore)
        ->and(CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->count())->toBe(1);
});

test('a debt due today is not overdue yet', function () {
    $this->travelTo('2026-10-10 15:00:00');
    $debt = Debt::factory()->create(['due_at' => '2026-10-10', 'is_paid' => false]);

    expect($debt->isOverdue())->toBeFalse();
});

test('a debt due yesterday is overdue', function () {
    $this->travelTo('2026-10-10 00:30:00');
    $debt = Debt::factory()->create(['due_at' => '2026-10-09', 'is_paid' => false]);

    expect($debt->isOverdue())->toBeTrue();
});
