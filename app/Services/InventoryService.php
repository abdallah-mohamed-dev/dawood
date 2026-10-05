<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the stock level and the single unit price of every material, plus the
 * movement log behind both — see docs/inventory-costing.md. A material has one
 * quantity and one price; there are no batches and no price layers, so every
 * quantity × price product in the system goes through cost() below.
 *
 * All quantities/costs passed in and out are raw scaled integers
 * (QuantityCast ×1000 / MoneyCast ×100), never floats.
 */
class InventoryService
{
    /**
     * Key used by stockValueByType() for materials saved without a type.
     */
    private const NO_TYPE_KEY = 'none';

    public function __construct(private readonly CashboxService $cashbox) {}

    /**
     * Put stock into a material and pay for it. The price given becomes the
     * material's price from now on — there is only ever one — so adding stock
     * at a new price re-prices what is already in the warehouse too.
     */
    public function addStock(Material $material, int $quantity, int $unitPrice, DateTimeInterface|string $date, PaymentMethod $method = PaymentMethod::Cash): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Add quantity must be greater than zero.');
        }

        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('Unit price must be greater than zero.');
        }

        // A tiny quantity at a tiny price (e.g. 0.001 × 0.01) can round down to
        // exactly 0 piastres — CashboxService rejects a 0 amount, so this must
        // be caught here with a clear message rather than letting that rejection
        // surface confusingly from inside the transaction.
        $cost = $this->cost($quantity, $unitPrice);
        if ($cost <= 0) {
            throw new InvalidArgumentException('التكلفة بتقرّب لصفر — زوّد الكمية أو سعر الوحدة.');
        }

        return DB::transaction(function () use ($material, $quantity, $unitPrice, $cost, $date, $method) {
            $material = $this->lockMaterial($material);

            $material->update([
                'unit_price' => $unitPrice,
                'quantity' => $material->getRawOriginal('quantity') + $quantity,
            ]);

            $movement = InventoryMovement::query()->create([
                'material_id' => $material->id,
                'type' => InventoryMovementType::In,
                'quantity' => $quantity,
                'cost' => $cost,
                'related_type' => null,
                'related_id' => null,
                'occurred_at' => $date,
            ]);

            $this->cashbox->recordOut($movement, $cost, CashboxTransactionKind::InventoryPurchase, $date, method: $method);

            return $movement;
        });
    }

    /**
     * Take stock out of a material and hand the money back — the material left
     * the shop, so the cashbox comes in. Not revenue: never touches
     * ProfitService.
     */
    public function reduceStock(Material $material, int $quantity, DateTimeInterface|string $date, PaymentMethod $method = PaymentMethod::Cash): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Reduce quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($material, $quantity, $date, $method) {
            $material = $this->lockMaterial($material);
            $available = $material->getRawOriginal('quantity');

            if ($available < $quantity) {
                throw new InsufficientStockException($material, $quantity, $available);
            }

            $amount = $this->cost($quantity, $material->getRawOriginal('unit_price'));
            if ($amount <= 0) {
                throw new InvalidArgumentException('التكلفة بتقرّب لصفر — المادة سعرها صفر.');
            }

            $material->update(['quantity' => $available - $quantity]);

            $movement = InventoryMovement::query()->create([
                'material_id' => $material->id,
                'type' => InventoryMovementType::Sold,
                'quantity' => $quantity,
                'cost' => $amount,
                'related_type' => null,
                'related_id' => null,
                'occurred_at' => $date,
            ]);

            $this->cashbox->recordIn($movement, $amount, CashboxTransactionKind::MaterialSale, $date, method: $method);

            return $movement;
        });
    }

    /**
     * Re-price a material without moving a single unit and without touching the
     * cashbox: the goods are still sitting in the shop, they are just worth a
     * different amount now. The value of the stock changes, the balance does not.
     */
    public function changePrice(Material $material, int $unitPrice): void
    {
        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('Unit price must be greater than zero.');
        }

        DB::transaction(function () use ($material, $unitPrice) {
            $this->lockMaterial($material)->update(['unit_price' => $unitPrice]);
        });
    }

    /**
     * Issue $quantity of $material to $related (a room requirement) all-or-nothing,
     * priced at the material's current unit price. No cashbox movement: the
     * money left when the stock was added, and issuing only moves it from the
     * warehouse to a room.
     *
     * @return array{cost: int}
     */
    public function issue(Material $material, int $quantity, Model $related, DateTimeInterface|string $date): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Issue quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($material, $quantity, $related, $date) {
            $material = $this->lockMaterial($material);
            $available = $material->getRawOriginal('quantity');

            if ($available < $quantity) {
                throw new InsufficientStockException($material, $quantity, $available);
            }

            $cost = $this->cost($quantity, $material->getRawOriginal('unit_price'));
            if ($cost <= 0) {
                throw new InvalidArgumentException('التكلفة بتقرّب لصفر — المادة سعرها صفر.');
            }

            $material->update(['quantity' => $available - $quantity]);

            InventoryMovement::query()->create([
                'material_id' => $material->id,
                'type' => InventoryMovementType::Out,
                'quantity' => $quantity,
                'cost' => $cost,
                'related_type' => $related::class,
                'related_id' => $related->getKey(),
                'occurred_at' => $date,
            ]);

            return ['cost' => $cost];
        });
    }

    /**
     * Give back everything issued to $related, at the quantity and the cost
     * recorded on each original `out` movement — re-pricing the material later
     * must not change what a past issue cost. The original `out` movements are
     * left untouched for audit; new `return` movements record the reversal.
     * No cashbox movement, same reason as issue().
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
                $material = $this->lockMaterial($movement->material);

                $material->update([
                    'quantity' => $material->getRawOriginal('quantity') + $movement->getRawOriginal('quantity'),
                ]);

                InventoryMovement::query()->create([
                    'material_id' => $movement->material_id,
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

    /**
     * What is actually on the shelf for $material right now. Read fresh rather
     * than from the caller's instance: a $material loaded before addStock() or
     * issue() ran still holds its old quantity, and callers routinely hold
     * exactly such a stale instance (a room's requirement, a blade view model).
     */
    public function currentStock(Material $material): int
    {
        return (int) Material::query()->whereKey($material->getKey())->toBase()->value('quantity');
    }

    /**
     * Current stock for several materials in one query — the single
     * implementation behind every "stock per material" listing (materials
     * index, a room's material requirements) instead of each controller
     * hand-rolling the same query. Pass null for every material in the
     * catalog. Raw scaled integers, not the cast decimal strings.
     *
     * @param  array<int>|null  $materialIds
     * @return Collection<int, int>
     */
    public function stockByMaterialIds(?array $materialIds = null): Collection
    {
        return Material::query()
            ->when($materialIds !== null, fn ($query) => $query->whereIn('id', $materialIds))
            ->toBase()
            ->pluck('quantity', 'id')
            ->map(fn ($quantity) => (int) $quantity);
    }

    /**
     * Total value of everything in the warehouse, each material at its own
     * current price — see stockValue() in docs/profit-calculation.md. An asset,
     * never a cost, until it is issued or sold.
     */
    public function stockValue(): int
    {
        return $this->stockRows()
            ->sum(fn ($material) => $this->cost((int) $material->quantity, (int) $material->unit_price));
    }

    /**
     * What one material on the shelf is worth right now — cost() behind a
     * public door, so the stock page can print a per-row value column without
     * re-deriving the mixed-scale rounding anywhere else. Read off the row's
     * RAW price (the cast hands back a decimal string) and a raw scaled
     * quantity, both of which the caller already has.
     */
    public function valueOf(Material $material, int $scaledQuantity): int
    {
        return $this->cost($scaledQuantity, (int) $material->getRawOriginal('unit_price'));
    }

    /**
     * The same total split by material type, for the stock page's summary
     * cards. Materials saved without a type are grouped under 'none' with a
     * dash label rather than dropped — they still have value. Only types that
     * actually hold stock appear, so the cards grow with the data instead of
     * rendering a row of zeroes.
     *
     * @return array{by_type: array<int|string, array{label: string, value: int}>, total: int}
     */
    public function stockValueByType(): array
    {
        $labels = MaterialType::query()->pluck('name', 'id');

        $byType = [];

        foreach ($this->stockRows() as $material) {
            $key = $material->material_type_id === null
                ? self::NO_TYPE_KEY
                : (int) $material->material_type_id;

            $byType[$key] ??= [
                'label' => $key === self::NO_TYPE_KEY ? '—' : ($labels->get($key) ?? '—'),
                'value' => 0,
            ];

            $byType[$key]['value'] += $this->cost((int) $material->quantity, (int) $material->unit_price);
        }

        return [
            'by_type' => $byType,
            // Summed off by_type rather than recomputed, so the cards can never
            // disagree with the total printed under them.
            'total' => array_sum(array_column($byType, 'value')),
        ];
    }

    /**
     * Every material that still holds stock, as raw rows — toBase() so the
     * scaled integers come back untouched by the model casts.
     *
     * @return Collection<int, object>
     */
    private function stockRows(): Collection
    {
        return Material::query()
            ->where('quantity', '>', 0)
            ->toBase()
            ->get(['material_type_id', 'quantity', 'unit_price']);
    }

    /**
     * The material as it is right now, locked for the rest of the
     * transaction — never the caller's instance, which may be stale.
     */
    private function lockMaterial(Material $material): Material
    {
        return Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
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
