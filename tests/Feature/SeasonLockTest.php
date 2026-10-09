<?php

use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Exceptions\RoomLockedException;
use App\Exceptions\SeasonClosedException;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\PartnerService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomService;
use App\Services\SeasonBackupService;
use App\Services\SeasonService;

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
    $this->seasons = app(SeasonService::class);
    $this->profit = app(ProfitService::class);
    $this->cashbox = app(CashboxService::class);

    // A completed room, an expense and a cost — all sealed by the close below.
    $this->room = Room::factory()->create(['status' => RoomStatus::Completed, 'sale_price' => 10_000]);
    $this->expense = Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory()->create()->id,
        'amount' => 500,
        'occurred_at' => '2026-02-01',
    ]);
    $this->cost = RoomCost::factory()->create(['room_id' => $this->room->id, 'type' => RoomCostType::Labor, 'amount' => 200]);
    $this->requirement = RoomMaterial::factory()->create([
        'room_id' => $this->room->id,
        'material_id' => Material::factory()->create()->id,
        'required_quantity' => 1,
        'issued_quantity' => 0,
        'cost' => 0,
    ]);

    $this->seasons->close('2026-06-30');
});

test('an expense from a closed season cannot be edited or deleted', function () {
    expect(fn () => app(ExpenseService::class)->update($this->expense->fresh(), 99_000))
        ->toThrow(SeasonClosedException::class);
    expect(fn () => app(ExpenseService::class)->delete($this->expense->fresh()))
        ->toThrow(SeasonClosedException::class);

    expect($this->expense->fresh()->getRawOriginal('amount'))->toBe(500);
});

test('an expense from a closed season is refused over HTTP with an Arabic message', function () {
    $this->actingAs($this->admin)
        ->put(route('expenses.update', $this->expense->fresh()), ['amount' => '990', 'payment_method' => 'cash'])
        ->assertSessionHas('error', 'الموسم ده مقفول، والبيانات دي للقراءة بس.');

    expect($this->expense->fresh()->getRawOriginal('amount'))->toBe(500);
});

test('a room cost from a closed season cannot be deleted', function () {
    expect(fn () => app(RoomCostService::class)->delete($this->cost->fresh()))
        ->toThrow(SeasonClosedException::class);
});

test('a new cost cannot be added to a sealed room', function () {
    expect(fn () => app(RoomCostService::class)->create($this->room->fresh(), RoomCostType::Other, 50, '2026-07-01'))
        ->toThrow(SeasonClosedException::class);
});

test('a sealed room cannot change status', function () {
    expect(fn () => app(RoomService::class)->changeStatus($this->room->fresh(), RoomStatus::InProgress))
        ->toThrow(SeasonClosedException::class);
});

test('a sealed room cannot be deleted', function () {
    expect(fn () => app(RoomService::class)->deleteRoom($this->room->fresh(), false))
        ->toThrow(SeasonClosedException::class);
});

test('a sealed room cannot take material requirements or issues', function () {
    $service = app(RoomMaterialService::class);

    expect(fn () => $service->addRequirement($this->room->fresh(), Material::factory()->create(), 500))
        ->toThrow(RoomLockedException::class);
    expect(fn () => $service->updateRequirement($this->requirement->fresh(), 2_000))
        ->toThrow(RoomLockedException::class);
    expect(fn () => $service->removeRequirement($this->requirement->fresh()))
        ->toThrow(RoomLockedException::class);
});

test('a customer payment on a completed room from a closed season still goes through (ق-5)', function () {
    $balance = $this->cashbox->balance();
    $profit = $this->profit->netProfit();

    $payment = app(CustomerPaymentService::class)->create($this->room->fresh(), 3_000, '2026-07-05');

    expect(CustomerPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
    expect($this->cashbox->balance())->toBe($balance + 3_000);
    expect($this->profit->netProfit())->toBe($profit);
});

test('a customer payment on a closed season room can also be edited and deleted (ق-5)', function () {
    $payment = app(CustomerPaymentService::class)->create($this->room->fresh(), 3_000, '2026-07-05');

    app(CustomerPaymentService::class)->update($payment->fresh(), 2_500);
    expect($payment->fresh()->getRawOriginal('amount'))->toBe(2_500);

    app(CustomerPaymentService::class)->delete($payment->fresh());
    expect(CustomerPayment::query()->whereKey($payment->id)->exists())->toBeFalse();
});

test('a closed season leaves the open season profit empty', function () {
    expect($this->profit->netProfit())->toBe(0);
});

test('a withdrawal from a closed season cannot be deleted', function () {
    $partner = Partner::factory()->create(['percentage' => 2_000]);
    $withdrawal = app(PartnerService::class)->withdraw($partner, 100, '2026-03-01');

    $this->seasons->close('2026-12-31');

    expect(fn () => app(PartnerService::class)->deleteWithdrawal($withdrawal->fresh()))
        ->toThrow(SeasonClosedException::class);
});
