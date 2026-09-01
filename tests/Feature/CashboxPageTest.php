<?php

use App\Enums\CashboxTransactionKind;
use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\InventoryService;
use App\Services\RoomCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot view the cashbox page', function () {
    $this->get(route('cashbox.index'))->assertRedirect(route('login'));
});

test('the cashbox page shows the current balance and totals', function () {
    $cashbox = app(CashboxService::class);
    $source = User::factory()->create();
    $cashbox->setOpeningBalance(500_000, '2026-01-01');
    $cashbox->recordIn($source, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');
    $cashbox->recordOut($source, 30_000, CashboxTransactionKind::Expense, '2026-01-03');

    $response = $this->actingAs($this->admin)->get(route('cashbox.index'));

    $response->assertOk();
    $response->assertSee('5,700.00 ج.م'); // balance: 5000 + 1000 - 300 = 5700 EGP
    $response->assertSee('رصيد افتتاحي');
    $response->assertSee('دفعة عميل');
    $response->assertSee('مصروف إداري');
});

test('setting the opening balance via the form creates a transaction', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '5000.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('cashbox.index'));
    $response->assertSessionHas('success');

    $transaction = CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->first();

    expect($transaction)->not->toBeNull();
    expect(app(CashboxService::class)->balance())->toBe(500_000);
});

test('resubmitting the opening balance form updates the same row', function () {
    $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '5000.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '7000.00',
        'occurred_at' => '2026-01-02',
        'payment_method' => 'cash',
    ]);

    expect(app(CashboxService::class)->balance())->toBe(700_000);
    expect(CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->count())->toBe(1);
});

test('the opening balance form rejects a negative amount', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '-10.00',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('the opening balance form rejects scientific notation as a clean validation error, not a server error', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '1e10',
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('the opening balance form rejects an unsafely large magnitude as a clean validation error, not a 500', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '9999999999999999.00', // 16 digits — over ScaledIntegerCast's safe limit
        'occurred_at' => '2026-01-01',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('the opening balance form rejects a missing date', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '10.00',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('occurred_at');
});

test('no direct CashboxTransaction::create() calls exist outside the CashboxService', function () {
    $offenders = [];

    $appFiles = collect(File::allFiles(app_path()))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->reject(fn ($file) => $file->getPathname() === app_path('Services/CashboxService.php'));

    foreach ($appFiles as $file) {
        $contents = File::get($file->getPathname());

        if (str_contains($contents, 'CashboxTransaction::create') || str_contains($contents, 'new CashboxTransaction(')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('the page splits incoming and outgoing into two independent tables', function () {
    $cashbox = app(CashboxService::class);
    $room = Room::factory()->for(Customer::factory(['name' => 'محمد أحمد']))->create(['room_type' => 'غرفة نوم']);
    $payment = CustomerPayment::factory()->for($room)->create();
    $expense = Expense::factory()->for(ExpenseCategory::factory(['name' => 'كهرباء']), 'category')->create();

    $cashbox->recordIn($payment, 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');
    $cashbox->recordOut($expense, 30_000, CashboxTransactionKind::Expense, '2026-01-03');

    $response = $this->actingAs($this->admin)->get(route('cashbox.index'));

    $response->assertOk()->assertSee('الداخل')->assertSee('الخارج');

    // The incoming row belongs to the left table and the outgoing one to the
    // right, so the first must appear before the "الخارج" heading and the
    // second after it.
    $html = $response->getContent();
    $split = strpos($html, 'text-sm font-semibold text-danger">الخارج');

    expect(strpos($html, 'محمد أحمد — غرفة نوم'))->toBeLessThan($split);
    expect(strpos($html, 'كهرباء'))->toBeGreaterThan($split);
});

test('paging one table does not move the other', function () {
    $cashbox = app(CashboxService::class);
    $room = Room::factory()->create();

    foreach (range(1, 30) as $i) {
        $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 1_000 * $i, CashboxTransactionKind::CustomerPayment, '2026-01-02');
    }
    $cashbox->recordOut(Expense::factory()->create(), 55_555, CashboxTransactionKind::Expense, '2026-01-03');

    // Page 2 of the incoming table must still show the single outgoing row.
    $this->actingAs($this->admin)
        ->get(route('cashbox.index', ['in_page' => 2]))
        ->assertOk()
        ->assertSee('555.55 ج.م');
});

test('the item column shows the detailed source, not the generic kind', function () {
    $cashbox = app(CashboxService::class);

    $room = Room::factory()->for(Customer::factory(['name' => 'محمد أحمد']))->create(['room_type' => 'غرفة نوم']);
    $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 100_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');

    $expense = Expense::factory()->for(ExpenseCategory::factory(['name' => 'كهرباء']), 'category')->create();
    $cashbox->recordOut($expense, 30_000, CashboxTransactionKind::Expense, '2026-01-03');

    $material = Material::factory()->create(['name' => 'خشب زان']);
    app(InventoryService::class)->purchase($material, 10_000, 10_000, '2026-01-04');

    $partner = Partner::factory()->create(['name' => 'عم سيد', 'percentage' => 1000]);
    $cashbox->recordOut(PartnerWithdrawal::factory()->for($partner)->create(), 5_000, CashboxTransactionKind::PartnerWithdrawal, '2026-01-05');

    app(RoomCostService::class)->create($room, RoomCostType::Labor, 20_000, '2026-01-06');

    $response = $this->actingAs($this->admin)->get(route('cashbox.index'));

    $response->assertOk();
    $response->assertSee('محمد أحمد — غرفة نوم');
    $response->assertSee('كهرباء');
    $response->assertSee('خشب زان');
    $response->assertSee('عم سيد');
    $response->assertSee('مصنعية — غرفة نوم');
});

test('a row whose source is gone falls back to the generic kind instead of breaking', function () {
    $cashbox = app(CashboxService::class);
    $expense = Expense::factory()->create();
    $cashbox->recordOut($expense, 30_000, CashboxTransactionKind::Expense, '2026-01-03');

    // Delete the source row directly, bypassing the service that would also
    // remove the cashbox row — simulating any future orphan.
    Expense::query()->whereKey($expense->id)->delete();

    $this->actingAs($this->admin)->get(route('cashbox.index'))
        ->assertOk()
        ->assertSee('مصروف إداري');
});

test('the page breaks the money down by payment method', function () {
    $cashbox = app(CashboxService::class);
    $room = Room::factory()->create();

    $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 700_000, CashboxTransactionKind::CustomerPayment, '2026-01-02', method: PaymentMethod::Instapay);
    $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 500_000, CashboxTransactionKind::CustomerPayment, '2026-01-02', method: PaymentMethod::Wallet);
    $cashbox->recordOut(Expense::factory()->create(), 300_000, CashboxTransactionKind::Expense, '2026-01-03', method: PaymentMethod::Card);

    $response = $this->actingAs($this->admin)->get(route('cashbox.index'));

    $response->assertOk();
    $response->assertSee('التقسيم حسب طريقة الدفع');
    foreach (PaymentMethod::cases() as $method) {
        $response->assertSee($method->label());
    }
    // The breakdown is presentation only — the balance stays one number.
    $response->assertSee('ليست محافظ منفصلة');

    $breakdown = $cashbox->breakdownByMethod();
    expect($breakdown['instapay'])->toBe(['in' => 700_000, 'out' => 0]);
    expect($breakdown['wallet'])->toBe(['in' => 500_000, 'out' => 0]);
    expect($breakdown['card'])->toBe(['in' => 0, 'out' => 300_000]);
    expect($breakdown['cash'])->toBe(['in' => 0, 'out' => 0]);
});

test('rows recorded before payment methods existed still render', function () {
    $cashbox = app(CashboxService::class);
    $cashbox->recordOut(Expense::factory()->create(), 30_000, CashboxTransactionKind::Expense, '2026-01-03');

    // Simulate a legacy row: the column is nullable exactly for these.
    CashboxTransaction::query()->update(['payment_method' => null]);

    $this->actingAs($this->admin)->get(route('cashbox.index'))
        ->assertOk()
        ->assertSee('حركات قديمة بدون طريقة دفع مسجَّلة');

    expect(app(CashboxService::class)->breakdownByMethod()['unknown'])->toBe(['in' => 0, 'out' => 30_000]);
});

test('the payment method is stored on the cashbox row and defaults to cash', function () {
    $cashbox = app(CashboxService::class);
    $room = Room::factory()->create();

    $explicit = $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 1_000, CashboxTransactionKind::CustomerPayment, '2026-01-02', method: PaymentMethod::Cheque);
    $implicit = $cashbox->recordOut(Expense::factory()->create(), 500, CashboxTransactionKind::Expense, '2026-01-03');

    expect($explicit->payment_method)->toBe(PaymentMethod::Cheque);
    expect($implicit->payment_method)->toBe(PaymentMethod::Cash);
});

test('updateFor changes the method only when one is given', function () {
    $cashbox = app(CashboxService::class);
    $payment = CustomerPayment::factory()->for(Room::factory())->create();
    $cashbox->recordIn($payment, 1_000, CashboxTransactionKind::CustomerPayment, '2026-01-02', method: PaymentMethod::Wallet);

    $cashbox->updateFor($payment, 2_000);
    $row = CashboxTransaction::query()->sole();
    expect($row->getRawOriginal('amount'))->toBe(2_000);
    expect($row->payment_method)->toBe(PaymentMethod::Wallet);

    $cashbox->updateFor($payment, 3_000, PaymentMethod::Card);
    $row = CashboxTransaction::query()->sole()->fresh();
    expect($row->payment_method)->toBe(PaymentMethod::Card);
});

test('listing many rows does not grow the query count', function () {
    $cashbox = app(CashboxService::class);
    $room = Room::factory()->create();

    $count = function () {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($this->admin)->get(route('cashbox.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 1_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');
    $withOne = $count();

    foreach (range(1, 15) as $i) {
        $cashbox->recordIn(CustomerPayment::factory()->for($room)->create(), 1_000, CashboxTransactionKind::CustomerPayment, '2026-01-02');
        $cashbox->recordOut(Expense::factory()->create(), 500, CashboxTransactionKind::Expense, '2026-01-03');
    }
    $withMany = $count();

    // morphWith keeps this flat; without it every row would fetch its own
    // source and that source's own relation.
    expect($withMany)->toBeLessThanOrEqual($withOne + 4);
});
