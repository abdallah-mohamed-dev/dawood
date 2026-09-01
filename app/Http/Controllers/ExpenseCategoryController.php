<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->paginate(25);

        return view('expenses.categories.index', ['categories' => $categories]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::query()->create($request->validated());

        return redirect()->route('expenses.categories.index')->with('success', 'تم إضافة البند.');
    }

    public function edit(ExpenseCategory $category): View
    {
        return view('expenses.categories.edit', ['category' => $category]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('expenses.categories.index')->with('success', 'تم تعديل البند.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        if ($category->expenses()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذا البند لأنه مستخدم في مصروفات مسجَّلة.');
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذا البند لأنه مستخدم في مصروفات مسجَّلة.');
        }

        return redirect()->route('expenses.categories.index')->with('success', 'تم حذف البند.');
    }
}
