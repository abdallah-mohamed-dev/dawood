<?php

namespace App\Http\Controllers\Inventory;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreMaterialRequest;
use App\Http\Requests\Inventory\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\MaterialType;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class MaterialController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $typeId = $request->integer('material_type_id');

        // 50 rather than the usual 25: this page is read by scanning for a
        // material, so longer pages mean less flipping. withQueryString keeps
        // an active search — and the type filter — alive on page two.
        $materials = Material::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($typeId > 0, fn ($query) => $query->where('material_type_id', $typeId))
            ->with('materialType')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        // Scoped to this page's materials — reading every material's
        // quantity to render 50 rows gets worse as the table grows.
        $stockByMaterial = $this->inventory->stockByMaterialIds($materials->pluck('id')->all());

        return view('inventory.materials.index', [
            'materials' => $materials,
            'stockByMaterial' => $stockByMaterial,
            // Priced by the service, not re-derived in the view — cost() is
            // private and the mixed-scale rounding has exactly one home.
            'valueByMaterial' => $materials->mapWithKeys(
                fn (Material $material): array => [$material->id => $this->inventory->valueOf($material, (int) ($stockByMaterial[$material->id] ?? 0))]
            ),
            // Deliberately NOT filtered: the cards are the shop's total worth,
            // and a search must not make the books look smaller.
            'stockSummary' => $this->inventory->stockValueByType(),
            'search' => $search,
            'selectedTypeId' => $typeId,
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        try {
            $unitPrice = MoneyCast::toScaledInt($request->string('unit_price')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['unit_price' => 'قيمة سعر الوحدة غير صالحة.']);
        }

        // Optional on quick-add: a material can be catalogued with no stock yet.
        $quantity = 0;

        if ($request->filled('quantity')) {
            try {
                $quantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
            } catch (InvalidArgumentException) {
                return back()->withInput()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.']);
            }
        }

        $method = $request->filled('payment_method')
            ? PaymentMethod::from($request->string('payment_method')->toString())
            : PaymentMethod::Cash;

        try {
            // One transaction: the material and the stock that paid for it are
            // a single fact. A material with a quantity and no row — or a cashbox
            // out with no material — is not a state this page can produce.
            DB::transaction(function () use ($request, $unitPrice, $quantity, $method) {
                $material = Material::query()->create([
                    ...$request->safe()->only(['name', 'unit', 'material_type_id']),
                    'unit_price' => $unitPrice,
                    // Explicit, not the DB column default: create() does not
                    // refresh the returned instance from DB-computed defaults,
                    // so getRawOriginal() inside addStock() would see null.
                    'quantity' => 0,
                ]);

                if ($quantity > 0) {
                    $this->inventory->addStock($material, $quantity, $unitPrice, now()->toDateString(), $method);
                }
            });
        } catch (InvalidArgumentException) {
            // addStock() refuses a quantity × price that rounds to zero —
            // CashboxService would reject a 0 amount from inside the
            // transaction and surface it as a raw 500.
            return back()->withInput()->withErrors(['quantity' => 'الكمية مع سعر الوحدة دول مع بعض تكلفتهم بتقرّب لصفر.']);
        }

        return redirect()->route('inventory.materials.index')->with('success', 'تم إضافة المادة.');
    }

    public function edit(Material $material): View
    {
        return view('inventory.materials.edit', [
            'material' => $material,
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        try {
            $unitPrice = MoneyCast::toScaledInt($request->string('unit_price')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['unit_price' => 'قيمة سعر الوحدة غير صالحة.']);
        }

        try {
            $newQuantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.']);
        }

        $method = PaymentMethod::from($request->string('payment_method')->toString());

        try {
            DB::transaction(function () use ($request, $material, $unitPrice, $newQuantity, $method) {
                // Re-read under the lock: the form was rendered from an
                // instance loaded before this request, and the delta below has
                // to be measured against what is actually in the box now.
                $locked = Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();

                $locked->update([
                    'name' => $request->string('name')->toString(),
                    'unit' => $request->string('unit')->toString(),
                    'material_type_id' => $request->integer('material_type_id'),
                ]);

                // Price FIRST. addStock()/reduceStock() read the material's own
                // unit_price, so a reduction computed against the old price
                // would put the wrong amount back in the cashbox — and the
                // increase/reduction is supposed to be worth the new price.
                if ($locked->getRawOriginal('unit_price') !== $unitPrice) {
                    $this->inventory->changePrice($locked, $unitPrice);
                }

                $delta = $newQuantity - $locked->getRawOriginal('quantity');

                if ($delta > 0) {
                    $this->inventory->addStock($locked, $delta, $unitPrice, now()->toDateString(), $method);
                } elseif ($delta < 0) {
                    try {
                        $this->inventory->reduceStock($locked, -$delta, now()->toDateString(), $method);
                    } catch (InsufficientStockException) {
                        throw new InsufficientStockException($locked, -$delta, $locked->getRawOriginal('quantity'));
                    }
                }
            });
        } catch (InsufficientStockException) {
            // Outside the transaction on purpose — returning from inside it
            // would COMMIT the price change above and leave the box re-priced
            // for a quantity edit that failed.
            return back()->withInput()->withErrors(['quantity' => 'الكمية المتاحة في المخزن أقل من الكمية المطلوبة.']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['quantity' => 'الكمية مع سعر الوحدة دول مع بعض تكلفتهم بتقرّب لصفر.']);
        }

        return redirect()->route('inventory.materials.index')->with('success', 'تم تعديل المادة.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if ($material->quantity > 0 || $material->roomMaterials()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأن عليها كمية في المخزن أو اتصرفت لغرفة.');
        }

        // The exists() check above and this delete() are not atomic — a stock
        // movement or a room requirement could appear in between. The FK's
        // restrictOnDelete() is the real guarantee; this catch just turns that
        // race from a raw 500 into the same friendly message.
        try {
            $material->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأن عليها كمية في المخزن أو اتصرفت لغرفة.');
        }

        return redirect()->route('inventory.materials.index')->with('success', 'تم حذف المادة.');
    }
}
