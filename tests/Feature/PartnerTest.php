<?php

use App\Enums\CashboxTransactionKind;
use App\Models\CashboxTransaction;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot access partners', function () {
    $this->get(route('partners.index'))->assertRedirect(route('login'));
});

test('the partner create and edit pages render', function () {
    $this->actingAs($this->admin)->get(route('partners.create'))->assertOk()->assertSee('إضافة شريك');

    $partner = Partner::factory()->create(['percentage' => 1500]);
    $this->actingAs($this->admin)->get(route('partners.edit', $partner))
        ->assertOk()
        ->assertSee('تعديل شريك')
        ->assertSee('15.00'); // prefilled percentage shown as a decimal
});

test('a partner can be created, storing its percentage scaled ×100', function () {
    $response = $this->actingAs($this->admin)->post(route('partners.store'), [
        'name' => 'أحمد',
        'percentage' => '33.33',
    ]);

    $response->assertRedirect(route('partners.index'));

    $partner = Partner::query()->where('name', 'أحمد')->firstOrFail();
    expect($partner->getRawOriginal('percentage'))->toBe(3333);
});

test('a percentage that is not a valid 2-decimal number is rejected', function () {
    $response = $this->actingAs($this->admin)->post(route('partners.store'), [
        'name' => 'أحمد',
        'percentage' => 'abc',
    ]);

    $response->assertSessionHasErrors('percentage');
    expect(Partner::query()->count())->toBe(0);
});

test('adding a partner whose percentage pushes the total over 100% is rejected', function () {
    Partner::factory()->create(['percentage' => 8000]); // 80%

    $response = $this->actingAs($this->admin)->post(route('partners.store'), [
        'name' => 'أحمد',
        'percentage' => '30', // 80% + 30% = 110%
    ]);

    $response->assertSessionHasErrors('percentage');
    expect(Partner::query()->count())->toBe(1);
});

test('a percentage that brings the total to exactly 100% is accepted', function () {
    Partner::factory()->create(['percentage' => 8000]); // 80%

    $this->actingAs($this->admin)->post(route('partners.store'), [
        'name' => 'أحمد',
        'percentage' => '20', // 80% + 20% = 100%
    ])->assertRedirect(route('partners.index'));

    expect(Partner::query()->count())->toBe(2);
});

test('updating a partner whose percentage pushes the total over 100% is rejected', function () {
    $first = Partner::factory()->create(['percentage' => 8000]); // 80%
    $second = Partner::factory()->create(['percentage' => 1000]); // 10%

    $response = $this->actingAs($this->admin)->put(route('partners.update', $second), [
        'name' => $second->name,
        'percentage' => '30', // other partners sum 80% + 30% = 110%
    ]);

    $response->assertSessionHasErrors('percentage');
    expect($second->fresh()->getRawOriginal('percentage'))->toBe(1000);
});

test('updating a partner excludes its own current percentage from the total cap', function () {
    $partner = Partner::factory()->create(['percentage' => 3000]); // the only partner

    $this->actingAs($this->admin)->put(route('partners.update', $partner), [
        'name' => $partner->name,
        'percentage' => '100',
    ])->assertRedirect(route('partners.show', $partner));

    expect($partner->fresh()->getRawOriginal('percentage'))->toBe(10_000);
});

test('recording a withdrawal creates a partner_withdrawal cashbox outflow', function () {
    $partner = Partner::factory()->create(['percentage' => 2000]);

    $response = $this->actingAs($this->admin)->post(route('partners.withdrawals.store', $partner), [
        'amount' => '2000.00',
        'occurred_at' => '2026-01-01',
        'note' => 'سحب شخصي',
    ]);

    $response->assertRedirect();

    $withdrawal = PartnerWithdrawal::query()->sole();
    expect($withdrawal->getRawOriginal('amount'))->toBe(200_000);

    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->source_type)->toBe(PartnerWithdrawal::class);
    expect($transaction->source_id)->toBe($withdrawal->id);
    expect($transaction->kind)->toBe(CashboxTransactionKind::PartnerWithdrawal);
    expect(app(CashboxService::class)->balance())->toBe(-200_000);
});

test('deleting a withdrawal restores the cashbox balance', function () {
    $partner = Partner::factory()->create(['percentage' => 2000]);
    $withdrawal = PartnerWithdrawal::factory()->for($partner)->create(['amount' => 200_000]);

    $response = $this->actingAs($this->admin)->delete(route('partners.withdrawals.destroy', [$partner, $withdrawal]));

    $response->assertRedirect();
    expect(PartnerWithdrawal::query()->count())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('the partner show page lists the share, withdrawals, and remaining', function () {
    Room::factory()->completed()->create(['sale_price' => 2_500_000]);
    $partner = Partner::factory()->create(['name' => 'أحمد', 'percentage' => 2000]);
    PartnerWithdrawal::factory()->for($partner)->create(['amount' => 200_000]); // 2,000 EGP

    $response = $this->actingAs($this->admin)->get(route('partners.show', $partner));

    $response->assertOk()
        ->assertSee('أحمد')
        ->assertSee('نسبة الشريك: 20.00%')
        ->assertSee('5,000.00 ج.م') // share
        ->assertSee('2,000.00 ج.م') // withdrawn
        ->assertSee('3,000.00 ج.م'); // remaining
});

test('deleting a partner with withdrawals is rejected', function () {
    $partner = Partner::factory()->create();
    PartnerWithdrawal::factory()->for($partner)->create();

    $response = $this->actingAs($this->admin)->delete(route('partners.destroy', $partner));

    $response->assertSessionHas('error');
    expect(Partner::query()->find($partner->id))->not->toBeNull();
});

test('the partners index shows the computed figures for each partner', function () {
    Room::factory()->completed()->create(['sale_price' => 2_500_000]);
    $partner = Partner::factory()->create(['name' => 'أحمد', 'percentage' => 2000]);

    $response = $this->actingAs($this->admin)->get(route('partners.index'));

    $response->assertOk()
        ->assertSee('أحمد')
        ->assertSee('20.00%')
        ->assertSee('5,000.00 ج.م'); // share of a 25,000 EGP net profit at 20%
});
