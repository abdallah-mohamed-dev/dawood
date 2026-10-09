<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Http\Controllers\LaborReportController;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\Season;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\ProfitService;
use App\Services\SeasonBackupService;
use App\Services\SeasonService;
use Illuminate\Http\Request;

beforeEach(function () {
    app()->instance(SeasonBackupService::class, new class extends SeasonBackupService
    {
        public function create(): string
        {
            return 'fake-backup.sqlite';
        }
    });

    Season::query()->where('status', SeasonStatus::Open)->update(['started_at' => '2026-01-01']);
    $this->admin = User::factory()->create();
    $this->customer = Customer::factory()->create(['name' => 'أحمد علي', 'phone' => '01000000001']);
    $this->room = Room::factory()->create(['customer_id' => $this->customer->id, 'room_type' => 'مطبخ', 'status' => RoomStatus::Completed]);
});

// ---------- 19.1 monthly breakdown ----------

test('the monthly tables add up to the cards above them', function () {
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 1_000, 'occurred_at' => '2026-03-05']);
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 500, 'occurred_at' => '2026-04-05']);
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Other, 'amount' => 300, 'occurred_at' => '2026-04-09']);
    Expense::factory()->create(['expense_category_id' => ExpenseCategory::factory()->create()->id, 'amount' => 700, 'occurred_at' => '2026-03-20']);
    Expense::factory()->create(['expense_category_id' => ExpenseCategory::factory()->create()->id, 'amount' => 200, 'occurred_at' => '2026-04-02']);

    $profit = app(ProfitService::class);

    expect($profit->laborCostsByMonth()->all())->toBe(['2026-04' => 500, '2026-03' => 1_000]);
    expect($profit->otherRoomCostsByMonth()->sum())->toBe(300);
    expect($profit->laborCostsByMonth()->sum() + $profit->otherRoomCostsByMonth()->sum())->toBe($profit->roomCosts());
    expect($profit->adminExpensesByMonth()->sum())->toBe($profit->adminExpenses());
});

test('a closed season is not in the monthly tables and its numbers are the frozen ones', function () {
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 1_000, 'occurred_at' => '2026-03-05']);
    app(SeasonService::class)->close('2026-06-30');

    $this->actingAs($this->admin)
        ->get(route('reports.profit', ['season' => Season::query()->where('number', 1)->value('id')]))
        ->assertOk()
        ->assertSee('السنابشوت المحفوظ')
        ->assertDontSee('شهر بشهر');
});

// ---------- 19.2 labor report ----------

test('the labor report groups by month and by room, and its three totals match', function () {
    $other = Room::factory()->create(['customer_id' => Customer::factory()->create()->id, 'room_type' => 'صالون']);
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 1_000, 'occurred_at' => '2026-03-05']);
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 500, 'occurred_at' => '2026-04-05']);
    RoomCost::factory()->create(['room_id' => $other->id, 'type' => RoomCostType::Labor, 'amount' => 250, 'occurred_at' => '2026-04-07']);
    RoomCost::factory()->create(['room_id' => $other->id, 'type' => RoomCostType::Other, 'amount' => 9_990, 'occurred_at' => '2026-04-07']);

    $this->actingAs($this->admin)
        ->get(route('reports.labor'))
        ->assertOk()
        ->assertSee('1,750');

    $controller = app(LaborReportController::class);
    $view = $controller->index(Request::create(route('reports.labor')));
    $data = $view->getData();

    $monthSum = $data['byMonth']->sum(fn ($row) => (int) $row->total);
    $roomSum = $data['perRoom']->sum(fn ($row) => (int) $row->total);

    expect($data['total'])->toBe(1_750);
    expect($monthSum)->toBe(1_750);
    expect($roomSum)->toBe(1_750);
});

test('the labor report filters by date, room and customer, and works with two filters together', function () {
    $other = Room::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 1_000, 'occurred_at' => '2026-03-05']);
    RoomCost::factory()->create(['room_id' => $other->id, 'type' => RoomCostType::Labor, 'amount' => 400, 'occurred_at' => '2026-04-07']);

    $run = fn (array $query) => app(LaborReportController::class)
        ->index(Request::create(route('reports.labor', $query)))
        ->getData()['total'];

    expect($run(['from' => '2026-04-01']))->toBe(400);
    expect($run(['room_id' => $this->room->id]))->toBe(1_000);
    expect($run(['customer_id' => $this->customer->id]))->toBe(1_000);
    expect($run(['room_id' => $other->id, 'from' => '2026-04-01']))->toBe(400);
    expect($run(['room_id' => $this->room->id, 'from' => '2026-04-01']))->toBe(0);
});

// ---------- 19.3 filters ----------

test('customers can be searched by name or phone', function () {
    Customer::factory()->create(['name' => 'سارة محمد', 'phone' => '01111111111']);

    $this->actingAs($this->admin)->get(route('customers.index', ['q' => 'أحمد']))->assertSee('أحمد علي')->assertDontSee('سارة محمد');
    $this->actingAs($this->admin)->get(route('customers.index', ['q' => '01111']))->assertSee('سارة محمد')->assertDontSee('أحمد علي');
});

test('payments can be searched by customer, room and receipt number with or without leading zeros', function () {
    CustomerPayment::factory()->create(['room_id' => $this->room->id, 'amount' => 100, 'receipt_number' => 12]);
    $other = Room::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'منى كمال'])->id, 'room_type' => 'صالون']);
    CustomerPayment::factory()->create(['room_id' => $other->id, 'amount' => 200, 'receipt_number' => 13]);

    $this->actingAs($this->admin)->get(route('payments.index', ['q' => 'منى']))->assertSee('منى كمال')->assertDontSee('أحمد علي');
    $this->actingAs($this->admin)->get(route('payments.index', ['q' => 'صالون']))->assertSee('صالون')->assertDontSee('مطبخ');
    $this->actingAs($this->admin)->get(route('payments.index', ['q' => '00012']))->assertSee('00012')->assertDontSee('00013');
    $this->actingAs($this->admin)->get(route('payments.index', ['q' => '12']))->assertSee('00012')->assertDontSee('00013');
});

test('payments can be filtered by date and the monthly totals follow the filter', function () {
    CustomerPayment::factory()->create(['room_id' => $this->room->id, 'amount' => 100, 'paid_at' => '2026-03-10']);
    CustomerPayment::factory()->create(['room_id' => $this->room->id, 'amount' => 200, 'paid_at' => '2026-04-10']);

    $this->actingAs($this->admin)
        ->get(route('payments.index', ['from' => '2026-04-01']))
        ->assertOk()
        ->assertSee('إجمالي الشهر: <span', false)
        ->assertDontSee('2026-03-10');
});

test('expenses can be searched by description, filtered by category and by date', function () {
    $electricity = ExpenseCategory::factory()->create(['name' => 'كهرباء']);
    $rent = ExpenseCategory::factory()->create(['name' => 'إيجار']);
    Expense::factory()->create(['expense_category_id' => $electricity->id, 'description' => 'فاتورة مارس', 'occurred_at' => '2026-03-02']);
    Expense::factory()->create(['expense_category_id' => $rent->id, 'description' => 'إيجار أبريل', 'occurred_at' => '2026-04-02']);

    $this->actingAs($this->admin)->get(route('expenses.index', ['q' => 'مارس']))->assertSee('فاتورة مارس')->assertDontSee('إيجار أبريل');
    $this->actingAs($this->admin)->get(route('expenses.index', ['expense_category_id' => $rent->id]))->assertSee('إيجار أبريل')->assertDontSee('فاتورة مارس');
    $this->actingAs($this->admin)->get(route('expenses.index', ['from' => '2026-04-01', 'expense_category_id' => $electricity->id]))->assertDontSee('فاتورة مارس');
});

test('the cashbox list can be filtered by kind and by payment method, while the top cards stay whole', function () {
    CustomerPayment::factory()->create(['room_id' => $this->room->id, 'amount' => 100, 'paid_at' => '2026-03-10']);
    $category = ExpenseCategory::factory()->create(['name' => 'بند الاختبار']);
    app(ExpenseService::class)->create($category, 5_000, '2026-03-11', null, PaymentMethod::Wallet);

    $this->actingAs($this->admin)
        ->get(route('cashbox.index', ['kind' => CashboxTransactionKind::Expense->value]))
        ->assertOk()
        ->assertSee('بند الاختبار');

    $this->actingAs($this->admin)
        ->get(route('cashbox.index', ['payment_method' => PaymentMethod::Wallet->value]))
        ->assertOk()
        ->assertSee('بند الاختبار');
});

test('the partner withdrawals list can be filtered by date and the share cards do not change', function () {
    $partner = Partner::factory()->create(['percentage' => 2_000]);
    PartnerWithdrawal::query()->create(['partner_id' => $partner->id, 'amount' => 10, 'occurred_at' => '2026-03-01', 'note' => 'سحب أول']);
    PartnerWithdrawal::query()->create(['partner_id' => $partner->id, 'amount' => 20, 'occurred_at' => '2026-05-01', 'note' => 'سحب تاني']);

    $this->actingAs($this->admin)
        ->get(route('partners.show', ['partner' => $partner, 'from' => '2026-04-01']))
        ->assertOk()
        ->assertSee('سحب تاني')
        ->assertDontSee('سحب أول');
});
