<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(): View
    {
        $expenses = Expense::query()
            ->with('category')
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25);

        return view('expenses.index', ['expenses' => $expenses]);
    }

    public function create(): View
    {
        return view('expenses.create', ['categories' => $this->categories()]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $category = ExpenseCategory::query()->findOrFail($request->integer('expense_category_id'));

        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $description = $request->filled('description') ? $request->string('description')->toString() : null;
            $this->expenses->create($category, $amount, $request->date('occurred_at'), $description);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف.');
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.edit', ['expense' => $expense]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $this->expenses->update($expense, $amount);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        } catch (RuntimeException) {
            // Defensive only — ExpenseService::update() calls
            // CashboxService::updateFor(), which throws a plain
            // RuntimeException if this expense's cashbox row is missing
            // (should be unreachable via normal create/delete flows).
            return back()->withInput()->withErrors(['amount' => 'حدث خطأ غير متوقع أثناء تحديث المصروف.']);
        }

        return redirect()->route('expenses.index')->with('success', 'تم تعديل المصروف.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->expenses->delete($expense);

        return redirect()->route('expenses.index')->with('success', 'تم حذف المصروف.');
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    private function categories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }
}
