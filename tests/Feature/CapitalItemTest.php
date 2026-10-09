<?php

use App\Models\CapitalItem;
use App\Models\CashboxTransaction;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\ProfitService;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function capitalPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'معدات',
        'amount' => '5000',
        'occurred_at' => '2026-03-01',
        'note' => null,
    ], $overrides);
}

test('guests are redirected to login', function () {
    $this->get(route('capital.index'))->assertRedirect(route('login'));
});

test('adding an item shows it in the table', function () {
    $this->actingAs($this->admin)
        ->post(route('capital.store'), capitalPayload(['name' => 'ماكينة قص']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->get(route('capital.index'))
        ->assertSee('ماكينة قص');
});

test('adding an item does not touch the cashbox balance or the transaction count', function () {
    $cashbox = app(CashboxService::class);
    $balance = $cashbox->balance();
    $rows = CashboxTransaction::query()->count();

    $this->actingAs($this->admin)->post(route('capital.store'), capitalPayload());

    expect($cashbox->balance())->toBe($balance);
    expect(CashboxTransaction::query()->count())->toBe($rows);
});

test('adding an item does not change net profit or revenue', function () {
    $profit = app(ProfitService::class);
    $net = $profit->netProfit();
    $revenue = $profit->revenue();

    $this->actingAs($this->admin)->post(route('capital.store'), capitalPayload(['amount' => '99999']));

    expect($profit->netProfit())->toBe($net);
    expect($profit->revenue())->toBe($revenue);
});

test('editing and deleting an item leaves the cashbox and profit untouched', function () {
    $cashbox = app(CashboxService::class);
    $profit = app(ProfitService::class);
    $item = CapitalItem::factory()->create(['amount' => 5_000]);
    $balance = $cashbox->balance();
    $net = $profit->netProfit();

    $this->actingAs($this->admin)
        ->put(route('capital.update', $item), capitalPayload(['amount' => '7000']))
        ->assertRedirect(route('capital.index'));
    expect($item->fresh()->getRawOriginal('amount'))->toBe(7_000);

    $this->actingAs($this->admin)->delete(route('capital.destroy', $item))->assertRedirect(route('capital.index'));
    expect(CapitalItem::query()->count())->toBe(0);

    expect($cashbox->balance())->toBe($balance);
    expect($profit->netProfit())->toBe($net);
});

test('the total is correct and reflects the date filter', function () {
    CapitalItem::factory()->create(['amount' => 5_000, 'occurred_at' => '2026-01-10']);
    CapitalItem::factory()->create(['amount' => 3_000, 'occurred_at' => '2026-03-10']);

    $this->actingAs($this->admin)
        ->get(route('capital.index'))
        ->assertSee('8,000');

    $this->actingAs($this->admin)
        ->get(route('capital.index', ['from' => '2026-03-01']))
        ->assertSee('3,000');
});

test('the total covers every matching row, not only the visible page', function () {
    CapitalItem::factory()->count(30)->create(['amount' => 100, 'occurred_at' => '2026-03-01']);

    $this->actingAs($this->admin)
        ->get(route('capital.index'))
        ->assertSee('3,000');
});

test('zero or negative amounts are rejected', function (string $amount) {
    $this->actingAs($this->admin)
        ->post(route('capital.store'), capitalPayload(['amount' => $amount]))
        ->assertSessionHasErrors(['amount']);

    expect(CapitalItem::query()->count())->toBe(0);
})->with(['0', '0', '-5']);

test('an item without a name is rejected in Arabic', function () {
    $this->actingAs($this->admin)
        ->post(route('capital.store'), capitalPayload(['name' => '']))
        ->assertSessionHasErrors(['name' => 'اسم البند مطلوب.']);
});

test('search narrows the list', function () {
    CapitalItem::factory()->create(['name' => 'معدات', 'note' => 'ماكينة']);
    CapitalItem::factory()->create(['name' => 'إيجار', 'note' => 'شهر مارس']);

    $this->actingAs($this->admin)
        ->get(route('capital.index', ['q' => 'ماكينة']))
        ->assertSee('معدات')
        ->assertDontSee('إيجار');
});
