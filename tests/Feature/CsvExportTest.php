<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\User;
use App\Services\CashboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/** Parses a streamed CSV response: strips the BOM and splits rows. */
function csvRows(TestResponse $response): array
{
    $content = $response->streamedContent();
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");

    return array_map('str_getcsv', array_values(array_filter(
        explode("\n", str_replace("\r\n", "\n", substr($content, 3))),
        fn (string $line) => $line !== '',
    )));
}

function queriesFor(string $url, User $admin): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($admin)->get($url)->streamedContent();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('guests are redirected to login from every export', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'exports.customers', 'exports.rooms', 'exports.materials', 'exports.purchases',
    'exports.payments', 'exports.expenses', 'exports.cashbox', 'exports.partners', 'exports.withdrawals',
]);

test('the backup page links to all nine readable exports', function () {
    $response = $this->actingAs($this->admin)->get(route('backup.index'))->assertOk();

    foreach (['customers', 'rooms', 'materials', 'purchases', 'payments', 'expenses', 'cashbox', 'partners', 'withdrawals'] as $name) {
        $response->assertSee(route('exports.'.$name), false);
    }
});

test('the customers export streams a BOM-prefixed CSV with Arabic headers', function () {
    $response = $this->actingAs($this->admin)->get(route('exports.customers'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))
        ->toContain('dawood-customers-'.now()->format('Y-m-d').'.csv');

    expect(csvRows($response)[0])->toBe([
        'الاسم', 'التليفون', 'عدد الغرف', 'إجمالي سعر البيع', 'المدفوع', 'المتبقي', 'تاريخ الإضافة',
    ]);
});

test('the customers export shows name, phone, room count and money in pounds', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد عبد الرحمن', 'phone' => '01000000000']);
    $room = Room::factory()->create(['customer_id' => $customer->id, 'sale_price' => 8500000]);
    CustomerPayment::factory()->create(['room_id' => $room->id, 'amount' => 2000000]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.customers')));

    expect($rows[1][0])->toBe('أحمد عبد الرحمن');
    expect($rows[1][1])->toBe('01000000000');
    expect($rows[1][2])->toBe('1');
    expect($rows[1][3])->toBe('85000.00');
    expect($rows[1][4])->toBe('20000.00');
    expect($rows[1][5])->toBe('65000.00');
});

test('the customers export has one row per customer under the header', function () {
    Customer::factory()->count(3)->create();

    expect(csvRows($this->actingAs($this->admin)->get(route('exports.customers'))))->toHaveCount(4);
});

test('the rooms export shows the customer name and phone instead of ids', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد عبد الرحمن', 'phone' => '01000000000']);
    Room::factory()->create(['customer_id' => $customer->id, 'room_type' => 'غرفة نوم', 'season_id' => null]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.rooms')));

    expect($rows[0])->toBe([
        'الغرفة', 'العميل', 'تليفون العميل', 'الحالة', 'الموسم', 'سعر البيع', 'تكلفة الخامات',
        'المصنعية', 'مصروفات أخرى', 'إجمالي التكلفة', 'الربح', 'المدفوع', 'المتبقي',
        'تاريخ الإنشاء', 'تاريخ بدء التنفيذ', 'تاريخ الاكتمال',
    ]);
    expect($rows[1][0])->toBe('غرفة نوم');
    expect($rows[1][1])->toBe('أحمد عبد الرحمن');
    expect($rows[1][2])->toBe('01000000000');
});

test('the rooms export writes money as pounds with two decimals', function () {
    $room = Room::factory()->create([
        'customer_id' => Customer::factory(),
        'sale_price' => 8500000,
        'status' => RoomStatus::Completed,
    ]);
    RoomMaterial::factory()->create(['room_id' => $room->id, 'cost' => 3000000]);

    $row = csvRows($this->actingAs($this->admin)->get(route('exports.rooms')))[1];

    expect($row[5])->toBe('85000.00');   // سعر البيع
    expect($row[6])->toBe('30000.00');   // تكلفة الخامات
    expect($row[9])->toBe('30000.00');   // إجمالي التكلفة
    expect($row[10])->toBe('55000.00');  // الربح
});

test('the rooms export shows the status in Arabic and leaves an empty season blank', function () {
    Room::factory()->create([
        'customer_id' => Customer::factory(),
        'status' => RoomStatus::InProgress,
        'season_id' => null,
    ]);

    $row = csvRows($this->actingAs($this->admin)->get(route('exports.rooms')))[1];

    expect($row[3])->toBe('تحت التنفيذ');
    expect($row[4])->toBe('');
});

test('the materials export shows type, quantity in thousandths and unit price in pounds', function () {
    $material = Material::factory()->create([
        'name' => 'خشب زان',
        'unit' => 'لوح',
        'quantity' => 1250,
        'unit_price' => 150000,
    ]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.materials')));

    expect($rows[0])->toBe(['الخامة', 'النوع', 'الوحدة', 'الكمية المتاحة', 'سعر الوحدة']);
    expect($rows[1])->toBe([$material->name, 'خامة', 'لوح', '1.250', '1500.00']);
});

test('the purchases export lists only inbound stock movements', function () {
    $material = Material::factory()->create(['name' => 'خشب زان', 'unit' => 'لوح']);
    InventoryMovement::factory()->create([
        'material_id' => $material->id,
        'quantity' => 2500,
        'cost' => 1500000,
        'occurred_at' => '2026-10-01',
    ]);
    InventoryMovement::factory()->out()->create(['material_id' => $material->id]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.purchases')));

    expect($rows[0])->toBe(['التاريخ', 'الخامة', 'الكمية', 'الوحدة', 'الإجمالي']);
    expect($rows)->toHaveCount(2);
    expect($rows[1])->toBe(['2026-10-01', 'خشب زان', '2.500', 'لوح', '15000.00']);
});

test('the payments export shows customer, room, method and receipt number', function () {
    $customer = Customer::factory()->create(['name' => 'أحمد عبد الرحمن']);
    $room = Room::factory()->create(['customer_id' => $customer->id, 'room_type' => 'غرفة نوم']);
    $payment = CustomerPayment::factory()->create([
        'room_id' => $room->id,
        'amount' => 2000000,
        'paid_at' => '2026-10-02',
        'receipt_number' => 7,
        'note' => 'عربون',
    ]);
    app(CashboxService::class)->recordIn($payment, 2000000, CashboxTransactionKind::CustomerPayment, '2026-10-02', method: PaymentMethod::Wallet);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.payments')));

    expect($rows[0])->toBe(['التاريخ', 'العميل', 'الغرفة', 'المبلغ', 'طريقة الدفع', 'رقم الإيصال', 'ملاحظة']);
    expect($rows[1])->toBe(['2026-10-02', 'أحمد عبد الرحمن', 'غرفة نوم', '20000.00', 'محفظة', '00007', 'عربون']);
});

test('the expenses export shows the category, amount, description and method', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'كهرباء']);
    $expense = Expense::factory()->create([
        'expense_category_id' => $category->id,
        'amount' => 50000,
        'occurred_at' => '2026-10-03',
        'description' => 'فاتورة سبتمبر',
    ]);
    app(CashboxService::class)->recordOut($expense, 50000, CashboxTransactionKind::Expense, '2026-10-03', method: PaymentMethod::Cash);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.expenses')));

    expect($rows[0])->toBe(['التاريخ', 'البند', 'المبلغ', 'الوصف', 'طريقة الدفع']);
    expect($rows[1])->toBe(['2026-10-03', 'كهرباء', '500.00', 'فاتورة سبتمبر', 'كاش']);
});

test('the cashbox export shows direction, kind and the real name of each movement', function () {
    $cashbox = app(CashboxService::class);
    $cashbox->setOpeningBalance(7700000, '2026-10-01');

    $customer = Customer::factory()->create(['name' => 'أحمد عبد الرحمن']);
    $room = Room::factory()->create(['customer_id' => $customer->id, 'room_type' => 'غرفة نوم']);
    $payment = CustomerPayment::factory()->create(['room_id' => $room->id, 'amount' => 2000000, 'paid_at' => '2026-10-02']);
    $cashbox->recordIn($payment, 2000000, CashboxTransactionKind::CustomerPayment, '2026-10-02');

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.cashbox')));

    expect($rows[0])->toBe(['التاريخ', 'الاتجاه', 'البند', 'البيان', 'المبلغ']);
    expect($rows[1])->toBe(['2026-10-01', 'داخل', 'رصيد افتتاحي', 'رصيد افتتاحي', '77000.00']);
    expect($rows[2])->toBe(['2026-10-02', 'داخل', 'دفعة عميل', 'أحمد عبد الرحمن — غرفة نوم', '20000.00']);
});

test('the partners export shows the share as a percentage and the open-season withdrawals', function () {
    $partner = Partner::factory()->create([
        'name' => 'محمد',
        'email' => 'partner@example.com',
        'percentage' => 2500,
    ]);
    PartnerWithdrawal::factory()->create(['partner_id' => $partner->id, 'amount' => 300000]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.partners')));

    expect($rows[0])->toBe(['الشريك', 'النسبة', 'الإيميل', 'المسحوب', 'تاريخ الإضافة']);
    expect($rows[1][0])->toBe('محمد');
    expect($rows[1][1])->toBe('25.00%');
    expect($rows[1][2])->toBe('partner@example.com');
    expect($rows[1][3])->toBe('3000.00');
});

test('the withdrawals export shows date, partner, amount and note', function () {
    $partner = Partner::factory()->create(['name' => 'محمد']);
    PartnerWithdrawal::factory()->create([
        'partner_id' => $partner->id,
        'amount' => 300000,
        'occurred_at' => '2026-10-04',
        'note' => 'مصاريف شخصية',
    ]);

    $rows = csvRows($this->actingAs($this->admin)->get(route('exports.withdrawals')));

    expect($rows[0])->toBe(['التاريخ', 'الشريك', 'المبلغ', 'ملاحظة']);
    expect($rows[1])->toBe(['2026-10-04', 'محمد', '3000.00', 'مصاريف شخصية']);
});

test('the rooms export filters by season when asked to', function () {
    $season = Season::factory()->create(['number' => 1, 'status' => SeasonStatus::Closed]);
    Room::factory()->create(['customer_id' => Customer::factory(), 'room_type' => 'في الموسم', 'season_id' => $season->id]);
    Room::factory()->create(['customer_id' => Customer::factory(), 'room_type' => 'الموسم المفتوح', 'season_id' => null]);

    $all = csvRows($this->actingAs($this->admin)->get(route('exports.rooms')));
    expect($all)->toHaveCount(3);

    $open = csvRows($this->actingAs($this->admin)->get(route('exports.rooms', ['season' => 'open'])));
    expect($open)->toHaveCount(2);
    expect($open[1][0])->toBe('الموسم المفتوح');

    $closed = csvRows($this->actingAs($this->admin)->get(route('exports.rooms', ['season' => $season->id])));
    expect($closed)->toHaveCount(2);
    expect($closed[1][0])->toBe('في الموسم');
});

test('the expenses export filters by season when asked to', function () {
    $season = Season::factory()->create(['number' => 1, 'status' => SeasonStatus::Closed]);
    Expense::factory()->create(['expense_category_id' => ExpenseCategory::factory(), 'season_id' => $season->id]);
    Expense::factory()->create(['expense_category_id' => ExpenseCategory::factory(), 'season_id' => null]);

    expect(csvRows($this->actingAs($this->admin)->get(route('exports.expenses', ['season' => 'open']))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.expenses', ['season' => $season->id]))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.expenses'))))->toHaveCount(3);
});

test('the payments export filters by its room season when asked to', function () {
    $season = Season::factory()->create(['number' => 1, 'status' => SeasonStatus::Closed]);
    $closedRoom = Room::factory()->create(['customer_id' => Customer::factory(), 'season_id' => $season->id]);
    $openRoom = Room::factory()->create(['customer_id' => Customer::factory(), 'season_id' => null]);
    CustomerPayment::factory()->create(['room_id' => $closedRoom->id]);
    CustomerPayment::factory()->create(['room_id' => $openRoom->id]);

    expect(csvRows($this->actingAs($this->admin)->get(route('exports.payments', ['season' => 'open']))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.payments', ['season' => $season->id]))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.payments'))))->toHaveCount(3);
});

test('the withdrawals export filters by season when asked to', function () {
    $season = Season::factory()->create(['number' => 1, 'status' => SeasonStatus::Closed]);
    $partner = Partner::factory()->create();
    PartnerWithdrawal::factory()->create(['partner_id' => $partner->id, 'season_id' => $season->id]);
    PartnerWithdrawal::factory()->create(['partner_id' => $partner->id, 'season_id' => null]);

    expect(csvRows($this->actingAs($this->admin)->get(route('exports.withdrawals', ['season' => 'open']))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.withdrawals', ['season' => $season->id]))))->toHaveCount(2);
    expect(csvRows($this->actingAs($this->admin)->get(route('exports.withdrawals'))))->toHaveCount(3);
});

test('the rooms export runs the same number of queries whatever the number of rooms', function () {
    $customer = Customer::factory()->create();
    $addRoom = function () use ($customer) {
        $room = Room::factory()->create(['customer_id' => $customer->id]);
        RoomMaterial::factory()->count(2)->create(['room_id' => $room->id]);
        CustomerPayment::factory()->create(['room_id' => $room->id]);
    };

    // Rows are created before each request: a request logs the user in, and
    // rows created afterwards would add queries of their own.
    $addRoom();
    $addRoom();
    $fewQueries = queriesFor(route('exports.rooms'), $this->admin);

    for ($i = 0; $i < 20; $i++) {
        $addRoom();
    }

    expect(queriesFor(route('exports.rooms'), $this->admin))->toBe($fewQueries);
});
