<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\RoomStatus;
use App\Models\CashboxTransaction;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\ProfitService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->roomMaterials = new RoomMaterialService($this->inventory);
    $this->expenses = new ExpenseService($this->cashbox);
    $this->profit = new ProfitService($this->inventory);
    $this->partners = new PartnerService($this->cashbox, $this->profit);
});

test('the reference scenario from docs/tasks.md: 25,000 EGP net profit and a 20% partner share to exactly 5,000 EGP', function () {
    Room::factory()->completed()->create(['sale_price' => 2_500_000]); // revenue-only, no materials, no expenses
    $partner = Partner::factory()->create(['percentage' => 2000]); // 20%

    expect($this->profit->netProfit())->toBe(2_500_000);
    expect($this->partners->share($partner))->toBe(500_000); // 5,000 EGP
});

test('withdrawing records a partner_withdrawal cashbox outflow and lowers the remaining share', function () {
    Room::factory()->completed()->create(['sale_price' => 2_500_000]);
    $partner = Partner::factory()->create(['percentage' => 2000]);

    $withdrawal = $this->partners->withdraw($partner, 200_000, '2026-01-01'); // 2,000 EGP

    expect($withdrawal->getRawOriginal('amount'))->toBe(200_000);
    expect($this->partners->totalWithdrawn($partner))->toBe(200_000);
    expect($this->partners->remaining($partner))->toBe(300_000);

    $transaction = CashboxTransaction::query()->sole();
    expect($transaction->source_type)->toBe(PartnerWithdrawal::class);
    expect($transaction->source_id)->toBe($withdrawal->id);
    expect($transaction->type)->toBe(CashboxTransactionType::Out);
    expect($transaction->kind)->toBe(CashboxTransactionKind::PartnerWithdrawal);
    expect($transaction->getRawOriginal('amount'))->toBe(200_000);
});

test('deleting a withdrawal restores the cashbox balance with no orphaned transaction', function () {
    Room::factory()->completed()->create(['sale_price' => 2_500_000]);
    $partner = Partner::factory()->create(['percentage' => 2000]);

    $withdrawal = $this->partners->withdraw($partner, 200_000, '2026-01-01');

    $this->partners->deleteWithdrawal($withdrawal);

    expect($this->partners->remaining($partner))->toBe(500_000);
    expect($this->cashbox->balance())->toBe(0);
    expect(CashboxTransaction::query()->count())->toBe(0);
    expect(PartnerWithdrawal::query()->count())->toBe(0);
});

test('a negative net profit gives a zero share and a negative remaining by exactly the withdrawn amount', function () {
    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 100_000, '2026-01-01'); // 1,000 EGP expense, no revenue
    $partner = Partner::factory()->create(['percentage' => 2000]);

    expect($this->profit->netProfit())->toBe(-100_000);

    $this->partners->withdraw($partner, 200_000, '2026-01-01');

    expect($this->partners->share($partner))->toBe(0);
    expect($this->partners->totalWithdrawn($partner))->toBe(200_000);
    expect($this->partners->remaining($partner))->toBe(-200_000);
});

test('share reads live from ProfitService — adding an expense changes the share on the next call', function () {
    Room::factory()->completed()->create(['sale_price' => 3_000_000]); // 30,000 EGP revenue
    $partner = Partner::factory()->create(['percentage' => 2000]); // 20%

    expect($this->partners->share($partner))->toBe(600_000); // 30,000 × 20% = 6,000 EGP

    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 200_000, '2026-01-01'); // 2,000 EGP expense

    expect($this->profit->netProfit())->toBe(2_800_000);
    expect($this->partners->share($partner))->toBe(560_000); // 28,000 × 20% = 5,600 EGP
});

test('a zero or negative withdrawal amount is rejected', function () {
    $partner = Partner::factory()->create();

    $this->partners->withdraw($partner, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('share respects Room status — a draft room contributes nothing to the share', function () {
    Room::factory()->create(['sale_price' => 2_500_000, 'status' => RoomStatus::Draft]);
    $partner = Partner::factory()->create(['percentage' => 2000]);

    expect($this->profit->netProfit())->toBe(0);
    expect($this->partners->share($partner))->toBe(0);
});
