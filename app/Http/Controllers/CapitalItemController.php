<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Http\Requests\StoreCapitalItemRequest;
use App\Http\Requests\UpdateCapitalItemRequest;
use App\Models\CapitalItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Capital is a record kept for display only. Nothing here touches the
 * cashbox or the profit calculation — see docs/capital.md.
 */
class CapitalItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $from = trim($request->string('from')->toString());
        $to = trim($request->string('to')->toString());

        $applyFilters = fn ($query) => $query
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('note', 'like', '%'.$search.'%')))
            ->when($from !== '', fn ($q) => $q->whereDate('occurred_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('occurred_at', '<=', $to));

        // The total runs over every filtered row, not the visible page — the
        // same rule as the monthly totals on the expenses page.
        $total = (int) $applyFilters(CapitalItem::query())->sum('amount');

        return view('capital.index', [
            'items' => $applyFilters(CapitalItem::query())
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'total' => $total,
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function store(StoreCapitalItemRequest $request): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        CapitalItem::query()->create([
            'name' => $request->string('name')->toString(),
            'amount' => $amount,
            'occurred_at' => $request->date('occurred_at'),
            'note' => $request->filled('note') ? $request->string('note')->toString() : null,
        ]);

        return back()->with('success', 'تم تسجيل البند.');
    }

    public function edit(CapitalItem $capitalItem): View
    {
        return view('capital.edit', ['item' => $capitalItem]);
    }

    public function update(UpdateCapitalItemRequest $request, CapitalItem $capitalItem): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        $capitalItem->update([
            'name' => $request->string('name')->toString(),
            'amount' => $amount,
            'occurred_at' => $request->date('occurred_at'),
            'note' => $request->filled('note') ? $request->string('note')->toString() : null,
        ]);

        return redirect()->route('capital.index')->with('success', 'تم تعديل البند.');
    }

    public function destroy(CapitalItem $capitalItem): RedirectResponse
    {
        $capitalItem->delete();

        return redirect()->route('capital.index')->with('success', 'تم حذف البند.');
    }
}
