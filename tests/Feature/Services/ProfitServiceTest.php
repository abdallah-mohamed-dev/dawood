<?php

use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Room;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
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
    $this->inventory->addStock($material, 10, 100, '2026-01-01'); // 10 @ 100 EGP

    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::InProgress]); // 30,000 EGP
    $rm = $this->roomMaterials->addRequirement($room, $material, 5);
    $this->roomMaterials->issue($rm, 5, '2026-01-02'); // cost = 500 EGP

    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 2_000, '2026-01-02'); // 2,000 EGP

    expect($this->profit->revenue())->toBe(0);
    expect($this->profit->costOfMaterials())->toBe(0);
    expect($this->profit->adminExpenses())->toBe(2_000);
    expect($this->profit->netProfit())->toBe(-2_000);
    expect($this->profit->workInProgress())->toBe(500);
});

test('completing the room moves revenue and cost into the profit calculation, out of WIP', function () {
    $material = Material::factory()->create();
    $this->inventory->addStock($material, 10, 120, '2026-01-01'); // 10 @ 120 EGP

    $material2 = Material::factory()->create();
    $this->inventory->addStock($material2, 3, 100, '2026-01-01'); // 3 @ 100 EGP

    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::InProgress]);
    $rm1 = $this->roomMaterials->addRequirement($room, $material2, 3);
    $this->roomMaterials->issue($rm1, 3, '2026-01-02'); // 3 @ 100 = 300 EGP
    $rm2 = $this->roomMaterials->addRequirement($room, $material, 2);
    $this->roomMaterials->issue($rm2, 2, '2026-01-02'); // 2 @ 120 = 240 EGP
    // total materials cost = 540 EGP = 54,000 piastres

    $category = ExpenseCategory::factory()->create();
    $this->expenses->create($category, 2_000, '2026-01-02'); // 2,000 EGP

    expect($this->profit->workInProgress())->toBe(540);

    $room->update(['status' => RoomStatus::Completed]);

    expect($this->profit->revenue())->toBe(30_000);
    expect($this->profit->costOfMaterials())->toBe(540);
    expect($this->profit->adminExpenses())->toBe(2_000);
    expect($this->profit->netProfit())->toBe(27_460); // 27,460 EGP
    expect($this->profit->workInProgress())->toBe(0);
});

test('netProfit does not change when a customer payment is added or deleted', function () {
    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::Completed]);

    $before = $this->profit->netProfit();

    $payment = $this->payments->create($room, 10_000, '2026-01-01');
    expect($this->profit->netProfit())->toBe($before);

    $this->payments->delete($payment);
    expect($this->profit->netProfit())->toBe($before);
});

test('a cancelled room contributes neither revenue nor cost', function () {
    $material = Material::factory()->create();
    $this->inventory->addStock($material, 5, 100, '2026-01-01');

    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::InProgress]);
    $rm = $this->roomMaterials->addRequirement($room, $material, 5);
    $this->roomMaterials->issue($rm, 5, '2026-01-02');

    $room->update(['status' => RoomStatus::Cancelled]);

    expect($this->profit->revenue())->toBe(0);
    expect($this->profit->costOfMaterials())->toBe(0);
    expect($this->profit->workInProgress())->toBe(0); // cancelled is neither draft/in_progress nor completed
});

test('stockValue after the reference scenario is 8 remaining units at the current price of 120 EGP', function () {
    $material = Material::factory()->create();
    $this->inventory->addStock($material, 3, 100, '2026-01-01'); // 3 @ 100 EGP
    $this->inventory->addStock($material, 10, 120, '2026-01-02'); // 10 @ 120 EGP

    $room = Room::factory()->create();
    $rm = $this->roomMaterials->addRequirement($room, $material, 5);
    $this->roomMaterials->issue($rm, 5, '2026-01-03');

    expect($this->profit->stockValue())->toBe(960); // 8 @ 120 EGP = 960 EGP
});

test('costs of a completed room reduce net profit', function () {
    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::Completed]);
    $costs = new RoomCostService($this->cashbox);
    $costs->create($room, RoomCostType::Labor, 5_000, '2026-01-05');
    $costs->create($room, RoomCostType::Other, 1_000, '2026-01-06');

    expect($this->profit->roomCosts())->toBe(6_000);
    expect($this->profit->cancelledRoomCosts())->toBe(0);
    expect($this->profit->workInProgress())->toBe(0);
    expect($this->profit->netProfit())->toBe(24_000);
});

test('costs of an in-progress room go to work in progress, not profit', function () {
    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::InProgress]);
    (new RoomCostService($this->cashbox))->create($room, RoomCostType::Labor, 5_000, '2026-01-05');

    expect($this->profit->roomCosts())->toBe(0);
    expect($this->profit->cancelledRoomCosts())->toBe(0);
    expect($this->profit->workInProgress())->toBe(5_000);
    expect($this->profit->netProfit())->toBe(0);
});

test('costs of a cancelled room are charged to profit immediately as a loss', function () {
    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::Cancelled]);
    (new RoomCostService($this->cashbox))->create($room, RoomCostType::Labor, 5_000, '2026-01-05');

    expect($this->profit->revenue())->toBe(0);
    expect($this->profit->roomCosts())->toBe(0);
    expect($this->profit->cancelledRoomCosts())->toBe(5_000);
    expect($this->profit->workInProgress())->toBe(0);
    expect($this->profit->netProfit())->toBe(-5_000);
});

test('every recorded cost lands in exactly one bucket: profit, WIP, or cancelled loss', function () {
    $costs = new RoomCostService($this->cashbox);

    $completed = Room::factory()->create(['sale_price' => 10_000, 'status' => RoomStatus::Completed]);
    $inProgress = Room::factory()->create(['sale_price' => 10_000, 'status' => RoomStatus::InProgress]);
    $draft = Room::factory()->create(['sale_price' => 10_000, 'status' => RoomStatus::Draft]);
    $cancelled = Room::factory()->create(['sale_price' => 10_000, 'status' => RoomStatus::Cancelled]);

    $costs->create($completed, RoomCostType::Labor, 1_000, '2026-01-05');
    $costs->create($inProgress, RoomCostType::Labor, 2_000, '2026-01-05');
    $costs->create($draft, RoomCostType::Other, 3_000, '2026-01-05');
    $costs->create($cancelled, RoomCostType::Other, 4_000, '2026-01-05');

    $buckets = $this->profit->roomCosts() + $this->profit->workInProgress() + $this->profit->cancelledRoomCosts();

    expect($buckets)->toBe(10_000);
    expect($this->cashbox->totalOut())->toBe(10_000);
});

test('the summary matches the individual figures once room costs exist', function () {
    $completed = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::Completed]);
    $cancelled = Room::factory()->create(['sale_price' => 10_000, 'status' => RoomStatus::Cancelled]);
    $costs = new RoomCostService($this->cashbox);
    $costs->create($completed, RoomCostType::Labor, 5_000, '2026-01-05');
    $costs->create($cancelled, RoomCostType::Labor, 2_000, '2026-01-05');

    $summary = $this->profit->summary();

    expect($summary['room_costs'])->toBe($this->profit->roomCosts());
    expect($summary['cancelled_room_costs'])->toBe($this->profit->cancelledRoomCosts());
    expect($summary['net_profit'])->toBe($this->profit->netProfit());
    expect($summary['work_in_progress'])->toBe($this->profit->workInProgress());
});

test('forRoom breaks a single room down and excludes admin expenses', function () {
    $material = Material::factory()->create();
    $this->inventory->addStock($material, 10, 100, '2026-01-01');

    $room = Room::factory()->create(['sale_price' => 30_000, 'status' => RoomStatus::InProgress]);
    $rm = $this->roomMaterials->addRequirement($room, $material, 5);
    $this->roomMaterials->issue($rm, 5, '2026-01-02'); // 50,000 piastres
    $room->update(['status' => RoomStatus::Completed]);

    $costs = new RoomCostService($this->cashbox);
    $costs->create($room, RoomCostType::Labor, 5_000, '2026-01-05');
    $costs->create($room, RoomCostType::Other, 1_000, '2026-01-06');

    // A large admin expense that must NOT touch the room's own profit.
    $this->expenses->create(ExpenseCategory::factory()->create(), 9_000, '2026-01-07');

    $breakdown = $this->profit->forRoom($room->fresh());

    expect($breakdown['sale_price'])->toBe(30_000);
    expect($breakdown['materials'])->toBe(500);
    expect($breakdown['labor'])->toBe(5_000);
    expect($breakdown['other'])->toBe(1_000);
    expect($breakdown['total_cost'])->toBe(6_500);
    expect($breakdown['profit'])->toBe(23_500);
});
