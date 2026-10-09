<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Services\CashboxService;
use App\Services\ProfitService;
use Illuminate\View\View;

/**
 * Reads only — every figure comes from an existing service method, except the
 * six-month cashbox series (monthlySeries) and the two lists below, which are
 * plain aggregates for display.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CashboxService $cashbox,
        private readonly ProfitService $profit,
    ) {}

    public function index(): View
    {
        $series = $this->cashbox->monthlySeries(6);
        $thisMonth = end($series);

        $topMaterials = RoomMaterial::query()
            ->with('material')
            ->where('issued_quantity', '>', 0)
            ->selectRaw('material_id, SUM(cost) as total')
            ->groupBy('material_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn (RoomMaterial $row) => [
                'label' => $row->material?->name ?? '—',
                'value' => (int) $row->total,
                'note' => '',
            ])
            ->all();

        $activeRooms = Room::query()
            ->with(['customer', 'customerPayments'])
            ->where('status', RoomStatus::InProgress)
            ->latest('started_at')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'series' => $series,
            'kpis' => [
                'balance' => $this->cashbox->balance(),
                'netProfit' => $this->profit->netProfit(),
                'workInProgress' => $this->profit->workInProgress(),
                'stockValue' => $this->profit->stockValue(),
                'inThisMonth' => $thisMonth['in'],
            ],
            'topMaterials' => $topMaterials,
            'activeRooms' => $activeRooms,
        ]);
    }
}
