<?php

use App\Enums\RoomStatus;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Room;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomMaterialService;

beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->roomMaterials = new RoomMaterialService($this->inventory);
    $this->payments = new CustomerPaymentService($this->cashbox);
    $this->expenses = new ExpenseService($this->cashbox);
    $this->profit = new ProfitService($this->inventory);
});

test('the reference scenario from docs/tasks.md: an in-progress room with issued materials and an expense', function () {
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 10_000, 10_000, '2026-01-01'); // 10 @ 100 EGP

    $room = Room::factory()->create(['sale_price' => 3_000_000, 'status' => RoomStatus::InProgress]); // 30,000 EGP
    $rm = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-02'); // cost = 500 EGP

    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 200_000, '2026-01-02'); // 2,000 EGP

    expect($this->profit->revenue())->toBe(0);
    expect($this->profit->costOfMaterials())->toBe(0);
    expect($this->profit->adminExpenses())->toBe(200_000);
    expect($this->profit->netProfit())->toBe(-200_000);
    expect($this->profit->workInProgress())->toBe(50_000);
});

test('completing the room moves revenue and cost into the profit calculation, out of WIP', function () {
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 10_000, 12_000, '2026-01-01'); // 10 @ 120 EGP → matches the 540 EGP reference cost when combined below

    $material2 = Material::factory()->create();
    $this->inventory->purchase($material2, 3_000, 10_000, '2026-01-01'); // 3 @ 100 EGP

    $room = Room::factory()->create(['sale_price' => 3_000_000, 'status' => RoomStatus::InProgress]);
    $rm1 = $this->roomMaterials->addRequirement($room, $material2, 3_000);
    $this->roomMaterials->issue($rm1, 3_000, '2026-01-02'); // 3 @ 100 = 300 EGP
    $rm2 = $this->roomMaterials->addRequirement($room, $material, 2_000);
    $this->roomMaterials->issue($rm2, 2_000, '2026-01-02'); // 2 @ 120 = 240 EGP
    // total materials cost = 540 EGP = 54,000 piastres

    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 200_000, '2026-01-02'); // 2,000 EGP

    expect($this->profit->workInProgress())->toBe(54_000);

    $room->update(['status' => RoomStatus::Completed]);

    expect($this->profit->revenue())->toBe(3_000_000);
    expect($this->profit->costOfMaterials())->toBe(54_000);
    expect($this->profit->adminExpenses())->toBe(200_000);
    expect($this->profit->netProfit())->toBe(2_746_000); // 27,460 EGP
    expect($this->profit->workInProgress())->toBe(0);
});

test('netProfit does not change when a customer payment is added or deleted', function () {
    $room = Room::factory()->create(['sale_price' => 3_000_000, 'status' => RoomStatus::Completed]);

    $before = $this->profit->netProfit();

    $payment = $this->payments->create($room, 1_000_000, '2026-01-01');
    expect($this->profit->netProfit())->toBe($before);

    $this->payments->delete($payment);
    expect($this->profit->netProfit())->toBe($before);
});

test('a cancelled room contributes neither revenue nor cost', function () {
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 5_000, 10_000, '2026-01-01');

    $room = Room::factory()->create(['sale_price' => 3_000_000, 'status' => RoomStatus::InProgress]);
    $rm = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-02');

    $room->update(['status' => RoomStatus::Cancelled]);

    expect($this->profit->revenue())->toBe(0);
    expect($this->profit->costOfMaterials())->toBe(0);
    expect($this->profit->workInProgress())->toBe(0); // cancelled is neither draft/in_progress nor completed
});

test('stockValue after the Task 4 FIFO reference scenario is 8 remaining units at 120 EGP', function () {
    $material = Material::factory()->create();
    $this->inventory->purchase($material, 3_000, 10_000, '2026-01-01'); // 3 @ 100 EGP
    $this->inventory->purchase($material, 10_000, 12_000, '2026-01-02'); // 10 @ 120 EGP

    $room = Room::factory()->create();
    $rm = $this->roomMaterials->addRequirement($room, $material, 5_000);
    $this->roomMaterials->issue($rm, 5_000, '2026-01-03');

    expect($this->profit->stockValue())->toBe(96_000); // 8 @ 120 EGP = 960 EGP
});
