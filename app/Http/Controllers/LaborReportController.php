<?php

namespace App\Http\Controllers;

use App\Enums\RoomCostType;
use App\Enums\SeasonStatus;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Labor (المصنعيات) across rooms, filterable by date, room and customer.
 * Read-only. The open season is the default; a closed season is read from its
 * rows as they were sealed.
 */
class LaborReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'from' => trim($request->string('from')->toString()),
            'to' => trim($request->string('to')->toString()),
            'room_id' => $request->integer('room_id'),
            'customer_id' => $request->integer('customer_id'),
            'season' => trim($request->string('season', 'open')->toString()),
        ];

        $base = fn () => $this->filtered(RoomCost::query()->where('type', RoomCostType::Labor), $filters);

        $total = (int) $base()->sum('amount');

        $byMonth = $base()
            ->selectRaw("strftime('%Y-%m', occurred_at) as month, COUNT(*) as payments, SUM(amount) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        // One grouped query for the per-room totals, then the rooms and customers in one more.
        $perRoom = $base()
            ->selectRaw('room_id, COUNT(*) as payments, SUM(amount) as total')
            ->groupBy('room_id')
            ->orderByDesc('total')
            ->get();

        $rooms = Room::query()
            ->with('customer')
            ->whereIn('id', $perRoom->pluck('room_id'))
            ->get()
            ->keyBy('id');

        return view('reports.labor', [
            'filters' => $filters,
            'total' => $total,
            'byMonth' => $byMonth,
            'perRoom' => $perRoom,
            'rooms' => $rooms,
            'details' => $base()->with('room.customer')->latest('occurred_at')->latest('id')->paginate(50)->withQueryString(),
            'roomOptions' => Room::query()->orderBy('id')->get(['id', 'room_type']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'seasons' => Season::query()->where('status', SeasonStatus::Closed)->orderByDesc('number')->get(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        $season = $filters['season'];

        return $query
            ->when($season === 'open', fn (Builder $q) => $q->whereNull('season_id'))
            ->when(ctype_digit($season), fn (Builder $q) => $q->where('season_id', (int) $season))
            ->when($filters['from'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $filters['to']))
            ->when($filters['room_id'] > 0, fn (Builder $q) => $q->where('room_id', $filters['room_id']))
            ->when($filters['customer_id'] > 0, fn (Builder $q) => $q->whereHas('room', fn (Builder $r) => $r->where('customer_id', $filters['customer_id'])));
    }
}
