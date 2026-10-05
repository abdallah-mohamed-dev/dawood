<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MovementController extends Controller
{
    /**
     * Every movement into and out of the warehouse, filterable. Read-only on
     * purpose: the movements are the audit trail written by
     * InventoryService, and nothing here may create, edit or delete one.
     */
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $type = trim($request->string('type')->toString());
        $from = trim($request->string('from')->toString());
        $to = trim($request->string('to')->toString());

        $applyFilters = function (Builder $query) use ($search, $type, $from, $to): Builder {
            return $query
                ->when($search !== '', fn (Builder $q) => $q->whereHas('material', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')))
                ->when($type !== '', fn (Builder $q) => $q->where('type', $type))
                ->when($from !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $from))
                ->when($to !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $to));
        };

        return view('inventory.movements.index', [
            // related.room: an `out` movement points at the room requirement
            // it filled, and the log shows the room's name on that row.
            'movements' => $applyFilters(InventoryMovement::query()->with(['material', 'related.room']))
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            // Over every row the filters return, not over the current page —
            // otherwise a month split across two pages would show a partial
            // total on each. See resources/views/expenses/index.blade.php.
            'monthlyTotals' => $this->monthlyTotals($applyFilters),
            'movementTypes' => InventoryMovementType::cases(),
            'search' => $search,
            'selectedType' => $type,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Total cost of every movement in each calendar month, across all the
     * filtered rows.
     *
     * @param  Closure(Builder): Builder  $applyFilters
     * @return Collection<string, int> keyed by "Y-m"
     */
    private function monthlyTotals(Closure $applyFilters): Collection
    {
        return $applyFilters(InventoryMovement::query())
            ->selectRaw("strftime('%Y-%m', occurred_at) as month, SUM(cost) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn ($total) => (int) $total);
    }
}
