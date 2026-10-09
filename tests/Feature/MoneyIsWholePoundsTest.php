<?php

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Models\CapitalItem;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RoomMaterialService;
use Illuminate\Support\Facades\DB;

/*
 * specs/022 — money is a whole number of pounds, everywhere, always.
 *
 * This file is the guard on that rule: it pins the cast, the validation, the
 * display and the stored columns, so a future change that reintroduces a
 * fraction anywhere fails here rather than in production.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the validation pattern accepts whole pounds and refuses any fraction', function () {
    foreach (['0', '7', '250', '1500000'] as $whole) {
        expect(preg_match(MoneyCast::validationPattern(), $whole))->toBe(1, $whole.' should be accepted');
    }

    foreach (['10.5', '10.50', '10.00', '0.01', '-5', '1e10', 'abc', ''] as $rejected) {
        expect(preg_match(MoneyCast::validationPattern(), $rejected))->toBe(0, $rejected.' should be refused');
    }
});

test('no amount is ever displayed with a decimal point', function () {
    foreach ([0, 1, 7, 250, 8_727, 1_500_000, -1, -8_727] as $amount) {
        expect(MoneyCast::toDecimalString($amount))->not->toContain('.');
        expect(MoneyCast::toDisplayString($amount))->not->toContain('.');
    }
});

/**
 * Every route that takes money, with a payload that is valid apart from the
 * fraction under test. Each one must be refused on that field alone.
 */
dataset('money endpoints', function () {
    return [
        'customer payment' => [
            fn () => route('rooms.payments.store', Room::factory()->create(['sale_price' => 50_000])),
            'post',
            fn () => ['amount' => '100.50', 'paid_at' => '2026-01-05', 'payment_method' => 'cash'],
            'amount',
            CustomerPayment::class,
        ],
        'room cost' => [
            fn () => route('rooms.costs.store', Room::factory()->create()),
            'post',
            fn () => ['type' => 'labor', 'amount' => '100.50', 'occurred_at' => '2026-01-05', 'payment_method' => 'cash'],
            'amount',
            RoomCost::class,
            'roomCost_labor',
        ],
        'admin expense' => [
            fn () => route('expenses.store'),
            'post',
            fn () => [
                'expense_category_id' => ExpenseCategory::factory()->create()->id,
                'amount' => '100.50',
                'occurred_at' => '2026-01-05',
                'payment_method' => 'cash',
            ],
            'amount',
            Expense::class,
        ],
        'partner withdrawal' => [
            fn () => route('partners.withdrawals.store', Partner::factory()->create(['percentage' => 5000])),
            'post',
            fn () => ['amount' => '100.50', 'occurred_at' => '2026-01-05', 'payment_method' => 'cash'],
            'amount',
            PartnerWithdrawal::class,
        ],
        'debt' => [
            fn () => route('debts.store'),
            'post',
            fn () => ['creditor' => 'مورد', 'amount' => '100.50', 'occurred_at' => '2026-01-05'],
            'amount',
            Debt::class,
        ],
        'capital item' => [
            fn () => route('capital.store'),
            'post',
            fn () => ['name' => 'منشار', 'amount' => '100.50', 'acquired_at' => '2026-01-05'],
            'amount',
            CapitalItem::class,
        ],
        'room sale price' => [
            fn () => route('customers.rooms.store', Customer::factory()->create()),
            'post',
            fn () => ['room_type' => 'غرفة نوم', 'sale_price' => '30000.50'],
            'sale_price',
            Room::class,
        ],
        'material unit price' => [
            fn () => route('inventory.materials.store'),
            'post',
            fn () => [
                'name' => 'خشب زان',
                'unit' => 'لوح',
                'unit_price' => '125.50',
                'quantity' => '10',
                'payment_method' => 'cash',
            ],
            'unit_price',
            Material::class,
        ],
    ];
});

test('every route that takes money refuses a fractional amount', function (
    Closure $url,
    string $method,
    Closure $payload,
    string $field,
    string $model,
    string $bag = 'default'
) {
    $before = $model::query()->count();

    $this->actingAs($this->admin)
        ->from('/')
        ->{$method}($url(), $payload())
        ->assertSessionHasErrors($field, errorBag: $bag);

    expect($model::query()->count())->toBe($before, 'a fractional amount must write nothing');
})->with('money endpoints');

test('every money column in the database holds a whole number after a full cycle', function () {
    $inventory = app(InventoryService::class);
    $roomMaterials = app(RoomMaterialService::class);

    // A complete pass through the system: purchase, issue, sell, pay, spend.
    $material = Material::factory()->create();
    $inventory->addStock($material, 10, 125, '2026-01-01', PaymentMethod::Cash);

    $room = Room::factory()->create(['sale_price' => 30_000]);
    $requirement = $roomMaterials->addRequirement($room, $material, 2);
    $roomMaterials->issue($requirement, 2, '2026-01-02');

    RoomCost::factory()->for($room)->create(['type' => RoomCostType::Labor, 'amount' => 3_000]);
    CustomerPayment::factory()->for($room)->create(['amount' => 7_000]);
    Expense::factory()->create(['amount' => 450]);

    $columns = [
        'cashbox_transactions' => ['amount'],
        'materials' => ['unit_price'],
        'inventory_movements' => ['cost'],
        'rooms' => ['sale_price', 'estimated_materials', 'estimated_accessories', 'estimated_labor', 'estimated_other'],
        'room_materials' => ['cost'],
        'room_costs' => ['amount'],
        'customer_payments' => ['amount'],
        'expenses' => ['amount'],
        'partner_withdrawals' => ['amount'],
        'debts' => ['amount'],
        'capital_items' => ['amount'],
        'seasons' => [
            'revenue', 'cost_of_materials', 'room_costs', 'cancelled_room_costs', 'admin_expenses',
            'net_profit', 'loss_carried_in', 'loss_carried_out', 'distributable_profit',
            'cashbox_balance_at_close', 'stock_value_at_close', 'wip_carried_forward',
        ],
        'season_partner_shares' => ['share_amount', 'carried_in', 'withdrawn', 'carried_out'],
    ];

    $checked = 0;

    foreach ($columns as $table => $moneyColumns) {
        foreach ($moneyColumns as $column) {
            foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $value) {
                expect($value)->toBe((int) $value, "{$table}.{$column} must hold a whole number of pounds");
                $checked++;
            }
        }
    }

    // The cycle above has to have written something, or this proves nothing.
    expect($checked)->toBeGreaterThan(5);
});

test('an issued cost is the quantity times the price, with no fraction to round', function () {
    $inventory = app(InventoryService::class);
    $roomMaterials = app(RoomMaterialService::class);

    // Quantities are whole units since specs/023, so a cost can no longer land
    // between two pounds: 3 units at 7 EGP is exactly 21.
    $material = Material::factory()->create();
    $inventory->addStock($material, 10, 7, '2026-01-01', PaymentMethod::Cash);

    $room = Room::factory()->create();
    $requirement = $roomMaterials->addRequirement($room, $material, 3);
    $roomMaterials->issue($requirement, 3, '2026-01-02');

    $cost = DB::table('room_materials')->where('id', $requirement->getKey())->value('cost');

    expect((int) $cost)->toBe(21)
        ->and($cost)->toBe((int) $cost);

    $movement = InventoryMovement::query()->latest('id')->first();
    expect($movement->getRawOriginal('cost'))->toBe(21);
});

test('the room page prints its figures without a single decimal point', function () {
    $room = Room::factory()->create(['sale_price' => 30_000]);
    CustomerPayment::factory()->for($room)->create(['amount' => 7_000]);

    $html = $this->actingAs($this->admin)->get(route('rooms.show', $room))->assertOk()->getContent();

    // Strip the currency mark, which carries a dot of its own.
    $figures = [];
    preg_match_all('/([\d,]+(?:\.\d+)?)\s*ج\.م/u', str_replace('ج.م', ' ج.م', $html), $figures);

    expect($figures[1])->not->toBeEmpty();

    foreach ($figures[1] as $figure) {
        expect($figure)->not->toContain('.', "printed figure {$figure} must have no fraction");
    }
});

test('the cashbox page prints its figures without a single decimal point', function () {
    CashboxTransaction::factory()->create(['amount' => 12_345]);

    $html = $this->actingAs($this->admin)->get(route('cashbox.index'))->assertOk()->getContent();

    $figures = [];
    preg_match_all('/([\d,]+(?:\.\d+)?)\s*ج\.م/u', str_replace('ج.م', ' ج.م', $html), $figures);

    expect($figures[1])->not->toBeEmpty();

    foreach ($figures[1] as $figure) {
        expect($figure)->not->toContain('.', "printed figure {$figure} must have no fraction");
    }
});
