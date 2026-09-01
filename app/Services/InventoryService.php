<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns purchasing, FIFO issuing, and the batch/movement bookkeeping that
 * backs it — see docs/inventory-costing.md. All quantities/costs passed in
 * and out are raw scaled integers (QuantityCast ×1000 / MoneyCast ×100),
 * never floats.
 */
class InventoryService
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function purchase(Material $material, int $quantity, int $unitCost, DateTimeInterface|string $date, PaymentMethod $method = PaymentMethod::Cash): InventoryBatch
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchase quantity must be greater than zero.');
        }

        if ($unitCost <= 0) {
            throw new InvalidArgumentException('Unit cost must be greater than zero.');
        }

        // A tiny quantity at a tiny unit cost (e.g. 0.001 × 0.01) can round
        // down to exactly 0 piastres — CashboxService rejects a 0 amount, so
        // this must be caught here with a clear message rather than letting
        // that rejection surface confusingly from inside the transaction.
        $cost = $this->cost($quantity, $unitCost);
        if ($cost <= 0) {
            throw new InvalidArgumentException('Purchase cost rounds to zero — increase the quantity or unit cost.');
        }

        return DB::transaction(function () use ($material, $quantity, $unitCost, $cost, $date, $method) {
            $batch = InventoryBatch::query()->create([
                'material_id' => $material->id,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $unitCost,
                'purchase_date' => $date,
            ]);

            InventoryMovement::query()->create([
                'material_id' => $material->id,
                'batch_id' => $batch->id,
                'type' => InventoryMovementType::In,
                'quantity' => $quantity,
                'cost' => $cost,
                'related_type' => null,
                'related_id' => null,
                'occurred_at' => $date,
            ]);

            $this->cashbox->recordOut($batch, $cost, CashboxTransactionKind::InventoryPurchase, $date, method: $method);

            return $batch;
        });
    }

    public function currentStock(Material $material): int
    {
        return (int) InventoryBatch::query()
            ->where('material_id', $material->id)
            ->sum('remaining_quantity');
    }

    /**
     * Current stock for several materials in one grouped query — the
     * single implementation behind every "stock per material" listing
     * (materials index, a room's material requirements) instead of each
     * controller hand-rolling the same SUM/GROUP BY. Pass null for every
     * material in the catalog.
     *
     * @param  array<int>|null  $materialIds
     * @return Collection<int, int>
     */
    public function stockByMaterialIds(?array $materialIds = null): Collection
    {
        return InventoryBatch::query()
            ->when($materialIds !== null, fn ($query) => $query->whereIn('material_id', $materialIds))
            ->selectRaw('material_id, SUM(remaining_quantity) as total')
            ->groupBy('material_id')
            ->pluck('total', 'material_id');
    }

    /**
     * Issue $quantity of $material FIFO across batches, all-or-nothing.
     *
     * @return array{cost: int, allocations: array<int, array{batch_id: int, quantity: int, cost: int}>}
     */
    public function issue(Material $material, int $quantity, Model $related, DateTimeInterface|string $date): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Issue quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($material, $quantity, $related, $date) {
            $batches = InventoryBatch::query()
                ->where('material_id', $material->id)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = $batches->sum(fn (InventoryBatch $batch) => $batch->getRawOriginal('remaining_quantity'));

            if ($available < $quantity) {
                throw new InsufficientStockException($material, $quantity, $available);
            }

            $remaining = $quantity;
            $totalCost = 0;
            $allocations = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $batchRemaining = $batch->getRawOriginal('remaining_quantity');
                $unitCost = $batch->getRawOriginal('unit_cost');
                $take = min($batchRemaining, $remaining);
                $cost = $this->cost($take, $unitCost);

                $batch->update(['remaining_quantity' => $batchRemaining - $take]);

                InventoryMovement::query()->create([
                    'material_id' => $material->id,
                    'batch_id' => $batch->id,
                    'type' => InventoryMovementType::Out,
                    'quantity' => $take,
                    'cost' => $cost,
                    'related_type' => $related::class,
                    'related_id' => $related->getKey(),
                    'occurred_at' => $date,
                ]);

                $allocations[] = ['batch_id' => $batch->id, 'quantity' => $take, 'cost' => $cost];
                $totalCost += $cost;
                $remaining -= $take;
            }

            return ['cost' => $totalCost, 'allocations' => $allocations];
        });
    }

    /**
     * Reverse every `out` movement previously issued to $related, crediting
     * each quantity back to the exact batch it was taken from (not the
     * cheapest/oldest available batch) — see "الإرجاع بعد حذف غرفة" in
     * docs/inventory-costing.md. The original `out` movements are left
     * untouched for audit; new `return` movements record the reversal.
     */
    public function returnIssued(Model $related): void
    {
        DB::transaction(function () use ($related) {
            $outMovements = InventoryMovement::query()
                ->where('related_type', $related::class)
                ->where('related_id', $related->getKey())
                ->where('type', InventoryMovementType::Out)
                ->get();

            foreach ($outMovements as $movement) {
                $batch = InventoryBatch::query()->whereKey($movement->batch_id)->lockForUpdate()->firstOrFail();

                $batch->update([
                    'remaining_quantity' => $batch->getRawOriginal('remaining_quantity') + $movement->getRawOriginal('quantity'),
                ]);

                InventoryMovement::query()->create([
                    'material_id' => $movement->material_id,
                    'batch_id' => $movement->batch_id,
                    'type' => InventoryMovementType::ReturnedToStock,
                    'quantity' => $movement->getRawOriginal('quantity'),
                    'cost' => $movement->getRawOriginal('cost'),
                    'related_type' => $related::class,
                    'related_id' => $related->getKey(),
                    'occurred_at' => now()->toDateString(),
                ]);
            }
        });
    }

    public function deletePurchase(InventoryBatch $batch): void
    {
        // The "untouched" check and the delete must happen inside the same
        // locked transaction as issue()'s allocation — otherwise a concurrent
        // issue() could deplete this batch between the check and the delete,
        // and cascadeOnDelete() would silently wipe out the resulting `out`
        // movement along with the batch (a real issue erased with no trace).
        DB::transaction(function () use ($batch) {
            $batch = InventoryBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();

            if ($batch->getRawOriginal('remaining_quantity') !== $batch->getRawOriginal('quantity')) {
                throw new InvalidArgumentException('Cannot delete a purchase batch that has already been issued from.');
            }

            $this->cashbox->removeFor($batch);
            $batch->delete();
        });
    }

    /**
     * Total value of all unissued stock, at each batch's own purchase cost —
     * see stockValue() in docs/profit-calculation.md. An asset, never a cost,
     * until issued.
     */
    public function stockValue(): int
    {
        return InventoryBatch::query()
            ->where('remaining_quantity', '>', 0)
            ->get(['remaining_quantity', 'unit_cost'])
            ->sum(fn (InventoryBatch $batch) => $this->cost(
                $batch->getRawOriginal('remaining_quantity'),
                $batch->getRawOriginal('unit_cost'),
            ));
    }

    /**
     * cost = round(scaledQuantity × unitCostPiastres / 1000), round half up.
     * See the "mixed-scale multiplication" note in docs/inventory-costing.md —
     * quantity is scaled ×1000, money ×100, so this division is mandatory.
     */
    private function cost(int $scaledQuantity, int $unitCostPiastres): int
    {
        $product = $scaledQuantity * $unitCostPiastres;
        $whole = intdiv($product, 1000);
        $remainder = $product % 1000;

        return $remainder * 2 >= 1000 ? $whole + 1 : $whole;
    }
}
