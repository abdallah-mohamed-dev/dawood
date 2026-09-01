<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomCost;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->roomMaterials = new RoomMaterialService($this->inventory);
    $this->payments = new CustomerPaymentService($this->cashbox);
    $this->expenses = new ExpenseService($this->cashbox);
    $this->profit = new ProfitService($this->inventory);
    $this->partners = new PartnerService($this->cashbox, $this->profit);
});

test('the full cycle from opening balance through partner withdrawal produces the exact reference numbers', function () {
    // 1. Opening balance 10,000 EGP
    $this->cashbox->setOpeningBalance(1_000_000, '2026-01-01');

    expect($this->cashbox->balance())->toBe(1_000_000);
    expect($this->profit->netProfit())->toBe(0);

    // 2. Purchase 3 @ 100 EGP → stock 3
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 3_000, 10_000, '2026-01-02');

    expect($this->inventory->currentStock($material))->toBe(3_000);
    expect($this->cashbox->balance())->toBe(970_000);
    expect($this->profit->netProfit())->toBe(0);

    // 3. Purchase 10 @ 120 EGP → stock 13
    $this->inventory->purchase($material, 10_000, 12_000, '2026-01-03');

    expect($this->inventory->currentStock($material))->toBe(13_000);
    expect($this->cashbox->balance())->toBe(850_000);
    expect($this->profit->netProfit())->toBe(0);

    // 4. Create the room at 30,000 EGP, in progress
    $customer = Customer::factory()->create();
    $room = Room::factory()->create([
        'customer_id' => $customer->id,
        'sale_price' => 3_000_000,
        'status' => RoomStatus::InProgress,
    ]);

    expect($this->cashbox->balance())->toBe(850_000);
    expect($this->profit->netProfit())->toBe(0);

    // 5. Issue 5 units to the room: FIFO cost 540 EGP (3 @ 100 + 2 @ 120), stock 8
    $roomMaterial = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($roomMaterial, 5_000, '2026-01-04');

    expect($this->inventory->currentStock($material))->toBe(8_000);
    expect($this->cashbox->balance())->toBe(850_000);
    expect($this->profit->netProfit())->toBe(0);
    expect($this->profit->workInProgress())->toBe(54_000);

    // 6. Customer payment 10,000 EGP
    $this->payments->create($room, 1_000_000, '2026-01-05');

    expect($this->cashbox->balance())->toBe(1_850_000);
    expect($this->profit->netProfit())->toBe(0);

    // 7. Admin expense 2,000 EGP
    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 200_000, '2026-01-06');

    expect($this->cashbox->balance())->toBe(1_650_000);
    expect($this->profit->netProfit())->toBe(-200_000);

    // 8. Complete the room — same plain model update as RoomController::updateStatus
    $room->update(['status' => RoomStatus::Completed]);

    expect($this->cashbox->balance())->toBe(1_650_000);
    expect($this->profit->netProfit())->toBe(2_746_000);

    // 9. Partner (20%) withdraws 2,000 EGP — share 5,492 EGP, remaining 3,492 EGP
    $partner = Partner::factory()->create(['percentage' => 2000]);
    $this->partners->withdraw($partner, 200_000, '2026-01-08');

    expect($this->cashbox->balance())->toBe(1_450_000);
    expect($this->profit->netProfit())->toBe(2_746_000);
    expect($this->partners->share($partner))->toBe(549_200);
    expect($this->partners->remaining($partner))->toBe(349_200);

    // Acceptance criterion 2: no orphaned transaction — the only row without a
    // source must be the single opening balance.
    $sourceLess = CashboxTransaction::query()->whereNull('source_id')->get();

    expect($sourceLess)->toHaveCount(1);
    expect($sourceLess->first()->kind)->toBe(CashboxTransactionKind::OpeningBalance);
    expect($sourceLess->first()->type)->toBe(CashboxTransactionType::In);
    expect($sourceLess->first()->getRawOriginal('amount'))->toBe(1_000_000);

    // Acceptance criterion 3: the cashbox balance matches an independently
    // summed total of the source table — a plain PHP sum over raw rows, not a
    // call to CashboxService::balance()'s own SQL aggregation, so a double
    // count would surface here.
    $rows = DB::table('cashbox_transactions')->select(['type', 'amount'])->get();
    $manualBalance = 0;

    foreach ($rows as $row) {
        $manualBalance += $row->type === CashboxTransactionType::In->value ? $row->amount : -$row->amount;
    }

    expect($this->cashbox->balance())->toBe($manualBalance);
});

test('a full cycle that includes labour and extra room costs keeps the cashbox and the profit report consistent', function () {
    $costs = new RoomCostService($this->cashbox);

    // Opening balance 20,000 EGP
    $this->cashbox->setOpeningBalance(2_000_000, '2026-01-01');

    // Buy 10 units @ 100 EGP = 1,000 EGP out
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 10_000, 10_000, '2026-01-02');

    $room = Room::factory()->for(Customer::factory())->create(['sale_price' => 3_000_000]);

    // Issue 5 units = 500 EGP of materials
    $roomMaterial = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($roomMaterial, 5_000, '2026-01-03');

    // Two labour payments (5,000 + 3,000 EGP) and one extra expense (500 EGP)
    $costs->create($room, RoomCostType::Labor, 500_000, '2026-01-04', 'دفعة أولى');
    $costs->create($room, RoomCostType::Labor, 300_000, '2026-01-05', 'دفعة ثانية');
    $costs->create($room, RoomCostType::Other, 50_000, '2026-01-06', 'نقل');

    // Cash: 20,000 − 1,000 − 5,000 − 3,000 − 500 = 10,500 EGP
    expect($this->cashbox->balance())->toBe(1_050_000);

    // Still in progress: nothing is a cost yet, everything sits in WIP.
    expect($this->profit->netProfit())->toBe(0);
    expect($this->profit->workInProgress())->toBe(900_000); // 500 materials + 8,500 labour/other

    // Customer pays 30,000 EGP in full
    $this->payments->create($room, 3_000_000, '2026-01-07');
    expect($this->cashbox->balance())->toBe(4_050_000);
    expect($this->profit->netProfit())->toBe(0);

    // Completing the room recognises revenue and every cost at once.
    $room->update(['status' => RoomStatus::Completed]);

    expect($this->profit->workInProgress())->toBe(0);
    expect($this->profit->roomCosts())->toBe(850_000);
    expect($this->profit->netProfit())->toBe(2_100_000); // 30,000 − 500 − 8,500 = 21,000 EGP

    // The cashbox is unchanged by a status flip — cash and accrual stay apart.
    expect($this->cashbox->balance())->toBe(4_050_000);

    // Every room cost owns exactly one cashbox row, and none is orphaned.
    expect(CashboxTransaction::query()->where('source_type', RoomCost::class)->count())->toBe(3);
    expect(CashboxTransaction::query()->whereNull('source_id')->count())->toBe(1);
});
