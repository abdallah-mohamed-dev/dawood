<?php

use App\Enums\InventoryMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// InventoryService::issue() needs a polymorphic "related" model to attach
// the resulting `out` movements to. RoomMaterial doesn't exist until Task 5
// — User stands in as "any model" here, matching the same approach used for
// CashboxService's polymorphic "source" in Task 2's tests.
beforeEach(function () {
    $this->cashbox = new CashboxService;
    $this->inventory = new InventoryService($this->cashbox);
    $this->material = Material::factory()->create();
});

test('a purchase creates an independent batch, an "in" movement, and a cashbox outflow', function () {
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');

    expect($batch->getRawOriginal('quantity'))->toBe(3_000);
    expect($batch->getRawOriginal('remaining_quantity'))->toBe(3_000);
    expect($batch->getRawOriginal('unit_cost'))->toBe(10_000);

    $movement = InventoryMovement::query()->where('batch_id', $batch->id)->sole();
    expect($movement->type)->toBe(InventoryMovementType::In);
    expect($movement->getRawOriginal('quantity'))->toBe(3_000);
    expect($movement->getRawOriginal('cost'))->toBe(30_000); // 3 * 100 EGP = 300 EGP = 30,000 piastres

    expect($this->cashbox->totalOut())->toBe(30_000);
});

test('two purchases of the same material at different prices create two independent batches', function () {
    $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');

    expect(InventoryBatch::query()->where('material_id', $this->material->id)->count())->toBe(2);
    expect($this->inventory->currentStock($this->material))->toBe(13_000);
});

test('the full FIFO reference scenario from docs/tasks.md produces exact expected numbers', function () {
    $related = User::factory()->create();

    $batchA = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01'); // 3 @ 100 EGP
    $batchB = $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02'); // 10 @ 120 EGP

    expect($this->inventory->currentStock($this->material))->toBe(13_000);

    $result = $this->inventory->issue($this->material, 5_000, $related, '2026-01-03');

    expect($result['cost'])->toBe(54_000); // 300 + 240 EGP = 540 EGP = 54,000 piastres
    expect($result['allocations'])->toBe([
        ['batch_id' => $batchA->id, 'quantity' => 3_000, 'cost' => 30_000],
        ['batch_id' => $batchB->id, 'quantity' => 2_000, 'cost' => 24_000],
    ]);

    expect($batchA->fresh()->getRawOriginal('remaining_quantity'))->toBe(0);
    expect($batchB->fresh()->getRawOriginal('remaining_quantity'))->toBe(8_000);
    expect($this->inventory->currentStock($this->material))->toBe(8_000);
});

test('a depleted batch is not deleted and remains in the database', function () {
    $related = User::factory()->create();
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');

    $this->inventory->issue($this->material, 3_000, $related, '2026-01-02');

    expect(InventoryBatch::query()->find($batch->id))->not->toBeNull();
    expect($batch->fresh()->getRawOriginal('remaining_quantity'))->toBe(0);
});

test('issuing more than available is rejected atomically and changes nothing', function () {
    $related = User::factory()->create();
    $batchA = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $batchB = $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');

    expect(fn () => $this->inventory->issue($this->material, 20_000, $related, '2026-01-03'))
        ->toThrow(InsufficientStockException::class);

    expect($batchA->fresh()->getRawOriginal('remaining_quantity'))->toBe(3_000);
    expect($batchB->fresh()->getRawOriginal('remaining_quantity'))->toBe(10_000);
    expect($this->inventory->currentStock($this->material))->toBe(13_000);
    expect(InventoryMovement::query()->where('type', InventoryMovementType::Out)->count())->toBe(0);
});

test('deleting an untouched purchase batch removes it and its cashbox transaction', function () {
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    expect($this->cashbox->balance())->toBe(-30_000);

    $this->inventory->deletePurchase($batch);

    expect(InventoryBatch::query()->find($batch->id))->toBeNull();
    expect($this->cashbox->balance())->toBe(0);
});

test('deleting the "in" movement is not needed manually — it cascades with the batch', function () {
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');

    $this->inventory->deletePurchase($batch);

    expect(InventoryMovement::query()->where('batch_id', $batch->id)->count())->toBe(0);
});

test('deleting a batch that has already been issued from is rejected', function () {
    $related = User::factory()->create();
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $this->inventory->issue($this->material, 1_000, $related, '2026-01-02');

    expect(fn () => $this->inventory->deletePurchase($batch))
        ->toThrow(InvalidArgumentException::class);

    expect(InventoryBatch::query()->find($batch->id))->not->toBeNull();
});

test('deletePurchase re-checks depletion at delete time, not against a stale caller-held instance', function () {
    // Simulates the race: $batch is held (as if fetched at the start of a
    // request) before a *different* transaction issues from it — mimicked
    // here by writing directly to the row, bypassing $batch's own state.
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');

    InventoryBatch::query()->whereKey($batch->id)->update(['remaining_quantity' => 2_000]);

    expect($batch->getRawOriginal('remaining_quantity'))->toBe(3_000); // still stale in memory

    expect(fn () => $this->inventory->deletePurchase($batch))
        ->toThrow(InvalidArgumentException::class);

    expect(InventoryBatch::query()->find($batch->id))->not->toBeNull();
});

test('deletePurchase throws ModelNotFoundException, not a silent no-op, when the batch is already gone', function () {
    // Simulates the narrow window where route-model-binding resolves a
    // batch that a concurrent request then deletes before this one's own
    // locked re-fetch runs — PurchaseController::destroy() catches exactly
    // this exception type.
    $batch = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    InventoryBatch::query()->whereKey($batch->id)->delete();

    expect(fn () => $this->inventory->deletePurchase($batch))
        ->toThrow(ModelNotFoundException::class);
});

test('deleting an untouched batch after another batch was partially consumed still works', function () {
    $batchA = $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $batchB = $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');
    $related = User::factory()->create();
    $this->inventory->issue($this->material, 3_000, $related, '2026-01-03');

    $this->inventory->deletePurchase($batchB);

    expect(InventoryBatch::query()->find($batchB->id))->toBeNull();
    expect(InventoryBatch::query()->find($batchA->id))->not->toBeNull();
});

test('purchase rejects a zero or negative quantity', function () {
    $this->inventory->purchase($this->material, 0, 10_000, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('purchase rejects a zero or negative unit cost', function () {
    $this->inventory->purchase($this->material, 1_000, 0, '2026-01-01');
})->throws(InvalidArgumentException::class);

test('purchase rejects a quantity/cost pair whose cost rounds down to zero, before writing anything', function () {
    // 0.001 units (scaled 1) at 0.01 EGP/unit (scaled 1) → 1×1/1000 rounds to 0.
    expect(fn () => $this->inventory->purchase($this->material, 1, 1, '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);

    expect(InventoryBatch::query()->count())->toBe(0);
    expect(InventoryMovement::query()->count())->toBe(0);
    expect($this->cashbox->balance())->toBe(0);
});

test('issue rejects a zero or negative quantity', function () {
    $related = User::factory()->create();
    $this->inventory->purchase($this->material, 1_000, 10_000, '2026-01-01');

    $this->inventory->issue($this->material, 0, $related, '2026-01-02');
})->throws(InvalidArgumentException::class);

test('issuing across three batches allocates FIFO in strict purchase-date order', function () {
    $related = User::factory()->create();
    $batchA = $this->inventory->purchase($this->material, 1_000, 10_000, '2026-01-01');
    $batchB = $this->inventory->purchase($this->material, 1_000, 20_000, '2026-01-02');
    $batchC = $this->inventory->purchase($this->material, 1_000, 30_000, '2026-01-03');

    $result = $this->inventory->issue($this->material, 2_500, $related, '2026-01-04');

    expect($result['allocations'])->toBe([
        ['batch_id' => $batchA->id, 'quantity' => 1_000, 'cost' => 10_000],
        ['batch_id' => $batchB->id, 'quantity' => 1_000, 'cost' => 20_000],
        ['batch_id' => $batchC->id, 'quantity' => 500, 'cost' => 15_000],
    ]);
});

test('cashbox balance after two purchases matches the opening balance minus their total cost', function () {
    $this->cashbox->setOpeningBalance(1_000_000, '2025-12-31');

    $this->inventory->purchase($this->material, 3_000, 10_000, '2026-01-01');
    $this->inventory->purchase($this->material, 10_000, 12_000, '2026-01-02');

    expect($this->cashbox->balance())->toBe(1_000_000 - 150_000);
});
