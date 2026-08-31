<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreMaterialRequest;
use App\Http\Requests\Inventory\UpdateMaterialRequest;
use App\Models\Material;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        $materials = Material::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();

        return view('inventory.materials.index', [
            'materials' => $materials,
            'stockByMaterial' => $this->inventory->stockByMaterialIds(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('inventory.materials.create');
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        Material::query()->create($request->validated());

        return redirect()->route('inventory.materials.index')->with('success', 'تم إضافة المادة.');
    }

    public function edit(Material $material): View
    {
        return view('inventory.materials.edit', ['material' => $material]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $material->update($request->validated());

        return redirect()->route('inventory.materials.index')->with('success', 'تم تعديل المادة.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if ($material->batches()->exists() || $material->roomMaterials()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأنها مستخدمة في دفعات مخزون أو غرف.');
        }

        // The exists() check above and this delete() are not atomic — a batch or
        // a room requirement could appear in between. The FK's restrictOnDelete()
        // is the real guarantee; this catch just turns that race from a raw 500
        // into the same friendly message.
        try {
            $material->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذه المادة لأنها مستخدمة في دفعات مخزون أو غرف.');
        }

        return redirect()->route('inventory.materials.index')->with('success', 'تم حذف المادة.');
    }
}
