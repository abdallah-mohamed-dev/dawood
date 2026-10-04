<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Debt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Plain CRUD on one table, so no Service — see the debts task. Nothing here
 * writes to the cashbox: a debt is a reminder, not a movement of money.
 */
class DebtController extends Controller
{
    public function index(): View
    {
        $outstanding = Debt::query()
            ->where('is_paid', false)
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $paid = Debt::query()
            ->where('is_paid', true)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        return view('debts.index', [
            'outstanding' => $outstanding,
            'outstandingTotal' => $outstanding->sum(fn (Debt $debt) => $debt->getRawOriginal('amount')),
            'paid' => $paid,
            'paidTotal' => $paid->sum(fn (Debt $debt) => $debt->getRawOriginal('amount')),
        ]);
    }

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        Debt::query()->create($this->attributes($request, $amount));

        return back()->with('success', 'تم تسجيل الدين.');
    }

    public function edit(Debt $debt): View
    {
        return view('debts.edit', ['debt' => $debt]);
    }

    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        $debt->update($this->attributes($request, $amount));

        return redirect()->route('debts.index')->with('success', 'تم تعديل الدين.');
    }

    public function destroy(Debt $debt): RedirectResponse
    {
        $debt->delete();

        return back()->with('success', 'تم حذف الدين.');
    }

    /**
     * Flips between outstanding and settled. Re-reads the row under a lock so
     * a double-click or a stale page cannot flip it twice.
     */
    public function togglePaid(Debt $debt): RedirectResponse
    {
        DB::transaction(function () use ($debt) {
            $debt = Debt::query()->whereKey($debt->getKey())->lockForUpdate()->firstOrFail();

            $settling = ! $debt->is_paid;

            $debt->update([
                'is_paid' => $settling,
                'paid_at' => $settling ? now()->toDateString() : null,
            ]);
        });

        return back()->with('success', 'تم تحديث حالة الدين.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, int $amount): array
    {
        return [
            'creditor' => $request->string('creditor')->toString(),
            'amount' => $amount,
            'incurred_at' => $request->date('incurred_at'),
            'due_at' => $request->filled('due_at') ? $request->date('due_at') : null,
            'note' => $request->filled('note') ? $request->string('note')->toString() : null,
        ];
    }
}
