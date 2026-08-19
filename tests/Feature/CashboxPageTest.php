<?php

use App\Enums\CashboxTransactionKind;
use App\Models\CashboxTransaction;
use App\Models\User;
use App\Services\CashboxService;
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
    ]);

    $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '7000.00',
        'occurred_at' => '2026-01-02',
    ]);

    expect(app(CashboxService::class)->balance())->toBe(700_000);
    expect(CashboxTransaction::query()->where('kind', CashboxTransactionKind::OpeningBalance)->count())->toBe(1);
});

test('the opening balance form rejects a negative amount', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '-10.00',
        'occurred_at' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('the opening balance form rejects scientific notation as a clean validation error, not a server error', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '1e10',
        'occurred_at' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(app(CashboxService::class)->balance())->toBe(0);
});

test('the opening balance form rejects a missing date', function () {
    $response = $this->actingAs($this->admin)->post(route('cashbox.opening-balance.store'), [
        'amount' => '10.00',
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
