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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $from = $request->date('from');
        $to = $request->date('to');
        $status = $request->string('status')->toString();

        $query = InventoryBatch::query()
            ->with('material')
            ->when($search !== '', fn (Builder $q) => $q->whereHas('material', fn (Builder $m) => $m->where('name', 'like', '%'.$search.'%')))
            ->when($from, fn (Builder $q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('purchase_date', '<=', $to))
            // available = nothing issued yet, depleted = all of it gone,
            // partial = somewhere in between.
            ->when($status === 'available', fn (Builder $q) => $q->whereColumn('remaining_quantity', '=', 'quantity'))
            ->when($status === 'depleted', fn (Builder $q) => $q->where('remaining_quantity', '=', 0))
            ->when($status === 'partial', fn (Builder $q) => $q->where('remaining_quantity', '>', 0)->whereColumn('remaining_quantity', '<', 'quantity'));

        return view('inventory.purchases.index', [
            // Summarised before ordering/paging, so the figures describe the
            // whole filtered set rather than the 25 rows on screen.
            'summary' => $this->inventory->purchasesSummary($query),
            'purchases' => (clone $query)->latest('purchase_date')->latest('id')->paginate(25)->withQueryString(),
            'materials' => $this->materials(),
            'filters' => [
                'q' => $search,
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'status' => in_array($status, ['available', 'partial', 'depleted'], true) ? $status : '',
            ],
        ]);
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
