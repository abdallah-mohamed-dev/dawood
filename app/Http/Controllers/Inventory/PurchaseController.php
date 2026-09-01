<?php

namespace App\Http\Controllers\Inventory;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StorePurchaseRequest;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): View
    {
        $purchases = InventoryBatch::query()
            ->with('material')
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(25);

        return view('inventory.purchases.index', ['purchases' => $purchases, 'materials' => $this->materials()]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $material = Material::query()->findOrFail($request->integer('material_id'));

        try {
            $quantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.']);
        }

        try {
            $unitCost = MoneyCast::toScaledInt($request->string('unit_cost')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['unit_cost' => 'قيمة سعر الوحدة غير صالحة.']);
        }

        // Checked here (not just left to InventoryService::purchase()'s own
        // guard) so each bad field gets its own attributed error instead of
        // a single message that always blames "quantity" regardless of
        // which value was actually zero.
        $errors = [];
        if ($quantity <= 0) {
            $errors['quantity'] = 'يجب أن تكون الكمية أكبر من صفر.';
        }
        if ($unitCost <= 0) {
            $errors['unit_cost'] = 'يجب أن يكون سعر الوحدة أكبر من صفر.';
        }
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        try {
            $this->inventory->purchase($material, $quantity, $unitCost, $request->date('purchase_date'), PaymentMethod::from($request->string('payment_method')->toString()));
        } catch (InvalidArgumentException) {
            // Covers the one case not pre-checked above: a valid-looking
            // quantity/cost pair whose product still rounds to 0 piastres.
            return back()->withInput()->withErrors([
                'quantity' => 'تكلفة هذه الكمية بهذا السعر تقل عن أصغر وحدة نقدية (قرش واحد).',
            ]);
        }

        return redirect()->route('inventory.purchases.index')->with('success', 'تم تسجيل عملية الشراء.');
    }

    public function destroy(InventoryBatch $purchase): RedirectResponse
    {
        try {
            $this->inventory->deletePurchase($purchase);
        } catch (InvalidArgumentException) {
            return back()->with('error', 'لا يمكن حذف عملية الشراء هذه لأنه تم الصرف من كميتها بالفعل.');
        } catch (ModelNotFoundException) {
            return back()->with('error', 'عملية الشراء هذه محذوفة بالفعل.');
        }

        return redirect()->route('inventory.purchases.index')->with('success', 'تم حذف عملية الشراء.');
    }

    /**
     * @return Collection<int, Material>
     */
    private function materials(): Collection
    {
        return Material::query()->orderBy('name')->get();
    }
}
