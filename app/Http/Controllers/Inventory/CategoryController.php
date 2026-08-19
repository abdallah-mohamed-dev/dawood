<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('materials')
            ->orderBy('name')
            ->get();

        return view('inventory.categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        return view('inventory.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'تم إضافة التصنيف.');
    }

    public function edit(Category $category): View
    {
        return view('inventory.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'تم تعديل التصنيف.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->materials()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذا التصنيف لأنه يحتوي على مواد مرتبطة به.');
        }

        // The exists() check above and this delete() are not atomic — a
        // material could be added to this category in between. The FK's
        // restrictOnDelete() is the real guarantee; this catch just turns
        // that race from a raw 500 into the same friendly message.
        try {
            $category->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذا التصنيف لأنه يحتوي على مواد مرتبطة به.');
        }

        return redirect()->route('inventory.categories.index')->with('success', 'تم حذف التصنيف.');
    }
}
