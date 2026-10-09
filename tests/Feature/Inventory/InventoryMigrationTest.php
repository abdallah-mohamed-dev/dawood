<?php

use App\Models\Material;
use App\Services\CashboxService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * specs/014 §14.1 — the schema teardown of FIFO: the stock moves onto
 * materials, the batches table disappears, and the purchase cashbox movements
 * have to follow it without the balance moving a single piastre.
 *
 * The old tables are re-created by rolling the five migrations back, then the
 * batch-era rows are written with DB::table (the model is gone by then), then
 * the migrations are re-run over that data.
 */
beforeEach(function () {
    $this->materialA = Material::factory()->create(['name' => 'خامة A']);
    $this->materialB = Material::factory()->create(['name' => 'خامة B']);

    // Roll back everything from spec 014.1 onward. Later migrations (added
    // after 014) sit on top of these, so they are counted too.
    $steps = DB::table('migrations')->where('migration', '>=', '2026_10_04_110000_add_stock_columns_to_materials_table')->count();
    $rollbackCode = Artisan::call('migrate:rollback', ['--step' => $steps]);
    expect($rollbackCode)->toBe(0, 'migrate:rollback failed: '.Artisan::output());

    $this->batchA1 = DB::table('inventory_batches')->insertGetId([
        'material_id' => $this->materialA->id,
        'quantity' => 3000,
        'remaining_quantity' => 1000,
        'unit_cost' => 10000,
        'purchase_date' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->batchA2 = DB::table('inventory_batches')->insertGetId([
        'material_id' => $this->materialA->id,
        'quantity' => 5000,
        'remaining_quantity' => 2000,
        'unit_cost' => 12000,
        'purchase_date' => '2026-02-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Every purchase writes exactly one `in` movement, and that movement's id
    // is where the cashbox row has to end up.
    $this->movementA1 = DB::table('inventory_movements')->insertGetId([
        'material_id' => $this->materialA->id,
        'batch_id' => $this->batchA1,
        'type' => 'in',
        'quantity' => 3000,
        'cost' => 30000,
        'related_type' => null,
        'related_id' => null,
        'occurred_at' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->movementA2 = DB::table('inventory_movements')->insertGetId([
        'material_id' => $this->materialA->id,
        'batch_id' => $this->batchA2,
        'type' => 'in',
        'quantity' => 5000,
        'cost' => 60000,
        'related_type' => null,
        'related_id' => null,
        'occurred_at' => '2026-02-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->cashboxA1 = DB::table('cashbox_transactions')->insertGetId([
        'type' => 'out',
        'amount' => 30000,
        'source_type' => 'App\Models\InventoryBatch',
        'source_id' => $this->batchA1,
        'kind' => 'inventory_purchase',
        'description' => null,
        'payment_method' => 'cash',
        'occurred_at' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->cashboxA2 = DB::table('cashbox_transactions')->insertGetId([
        'type' => 'out',
        'amount' => 60000,
        'source_type' => 'App\Models\InventoryBatch',
        'source_id' => $this->batchA2,
        'kind' => 'inventory_purchase',
        'description' => null,
        'payment_method' => 'cash',
        'occurred_at' => '2026-02-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->balanceBefore = app(CashboxService::class)->balance();
    $this->outBefore = app(CashboxService::class)->totalOut();
    $this->rowsBefore = DB::table('cashbox_transactions')->count();

    $migrateCode = Artisan::call('migrate');
    expect($migrateCode)->toBe(0, 'migrate failed: '.Artisan::output());
});

test('the material keeps what its batches still hold', function () {
    $material = DB::table('materials')->where('id', $this->materialA->id)->first();

    // 1000 still left from batch A1 + 2000 from batch A2 = 3000 thousandths,
    // which specs/023's migration turns into 3 whole units.
    expect((int) $material->quantity)->toBe(3);
});

test('the material takes the newest batch price', function () {
    $material = DB::table('materials')->where('id', $this->materialA->id)->first();

    // batch A2 (2026-02-01) is newer than A1 (2026-01-01): 12000 piastres,
    // not 10000 — and specs/022's migration turns that into 120 pounds.
    expect((int) $material->unit_price)->toBe(120);
});

test('a material that never had a batch ends up at zero', function () {
    $material = DB::table('materials')->where('id', $this->materialB->id)->first();

    expect((int) $material->quantity)->toBe(0)
        ->and((int) $material->unit_price)->toBe(0);
});

test('the cashbox balance survives the migrations, rescaled into whole pounds', function () {
    // The rows went in as the batch era wrote them — piastres. specs/022's
    // migration runs in the same batch, so every amount comes out divided by
    // 100 and nothing else about the balance moves.
    expect($this->balanceBefore)->toBe(-90_000)
        ->and($this->outBefore)->toBe(90_000)
        ->and(app(CashboxService::class)->balance())->toBe(intdiv($this->balanceBefore, 100))
        ->and(app(CashboxService::class)->totalOut())->toBe(intdiv($this->outBefore, 100));
});

test('no cashbox row is deleted or re-amounted', function () {
    expect(DB::table('cashbox_transactions')->count())->toBe($this->rowsBefore);

    foreach ([[$this->cashboxA1, 300], [$this->cashboxA2, 600]] as [$id, $amount]) {
        $row = DB::table('cashbox_transactions')->where('id', $id)->first();

        expect((int) $row->amount)->toBe($amount)
            ->and($row->type)->toBe('out')
            ->and($row->kind)->toBe('inventory_purchase')
            ->and((string) $row->occurred_at)->toStartWith('2026-0');
    }
});

test('every purchase cashbox row now points at the matching in movement', function () {
    foreach ([[$this->cashboxA1, $this->movementA1], [$this->cashboxA2, $this->movementA2]] as [$cashboxId, $movementId]) {
        $row = DB::table('cashbox_transactions')->where('id', $cashboxId)->first();

        expect($row->source_type)->toBe('App\Models\InventoryMovement')
            ->and((int) $row->source_id)->toBe($movementId);
    }

    expect(DB::table('cashbox_transactions')->where('source_type', 'App\Models\InventoryBatch')->count())->toBe(0);
});

test('the movements themselves survive with their amounts', function () {
    expect(DB::table('inventory_movements')->count())->toBe(2);

    $movement = DB::table('inventory_movements')->where('id', $this->movementA1)->first();

    // Seeded as 3000 thousandths; specs/023's migration makes that 3 units.
    expect((int) $movement->quantity)->toBe(3)
        ->and((int) $movement->cost)->toBe(300)
        ->and($movement->type)->toBe('in');
});

test('the batch machinery is gone from the schema', function () {
    expect(Schema::hasColumn('inventory_movements', 'batch_id'))->toBeFalse()
        ->and(Schema::hasTable('inventory_batches'))->toBeFalse()
        ->and(Schema::hasColumn('materials', 'quantity'))->toBeTrue()
        ->and(Schema::hasColumn('materials', 'unit_price'))->toBeTrue();
});
