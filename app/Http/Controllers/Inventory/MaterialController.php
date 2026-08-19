<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreMaterialRequest;
use App\Http\Requests\Inventory\UpdateMaterialRequest;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(): View
    {
        $materials = Material::query()
            ->with('category')
            ->join('categories', 'categories.id', '=', 'materials.category_id')
            ->orderBy('categories.name')
            ->orderBy('materials.name')
            ->select('materials.*')
            ->get();

        // One grouped aggregate instead of calling currentStock() per row.
        $stockByMaterial = InventoryBatch::query()
            ->selectRaw('material_id, SUM(remaining_quantity) as total')
            ->groupBy('material_id')
            ->pluck('total', 'material_id');

        return view('inventory.materials.index', [
            'materials' => $materials,
            'stockByMaterial' => $stockByMaterial,
        ]);
    }

    public function create(): View
    {
        return view('inventory.materials.create', ['categories' => $this->categories()]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        Material::query()->create($request->validated());

        return redirect()->route('inventory.materials.index')->with('success', 'تم إضافة المادة.');
    }

    public function edit(Material $material): View
    {
        return view('inventory.materials.edit', [
            'material' => $material,
            'categories' => $this->categories(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $material->update($request->validated());

        return redirect()->route('inventory.materials.index')->with('success', 'تم تعديل المادة.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if ($material->batches()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأنها تحتوي على دفعات مخزون مرتبطة بها.');
        }

        // See CategoryController::destroy() — same check-then-delete race,
        // same defense: the FK restrictOnDelete() is the real guarantee.
        try {
            $material->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأنها تحتوي على دفعات مخزون مرتبطة بها.');
        }

        return redirect()->route('inventory.materials.index')->with('success', 'تم حذف المادة.');
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }
}
