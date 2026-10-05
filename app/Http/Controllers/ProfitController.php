<?php

namespace App\Http\Controllers;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Services\CashboxService;
use App\Services\ProfitService;
use App\Services\SeasonService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitController extends Controller
{
    public function __construct(
        private readonly ProfitService $profit,
        private readonly CashboxService $cashbox,
        private readonly SeasonService $seasons,
    ) {}

    /**
     * The open season is live. A closed season shows only its frozen snapshot
     * (specs/012 ق-7) — its breakdowns by month are never recomputed.
     */
    public function index(Request $request): View
    {
        $seasonParam = trim($request->string('season', 'open')->toString());
        $closed = ctype_digit($seasonParam)
            ? Season::query()->where('status', SeasonStatus::Closed)->find((int) $seasonParam)
            : null;

        $season = Season::query()->where('status', SeasonStatus::Open)->firstOrFail();
        $seasonOptions = Season::query()->where('status', SeasonStatus::Closed)->orderByDesc('number')->get();

        if ($closed !== null) {
            return view('reports.profit', $this->snapshotData($closed, $seasonOptions));
        }

        $summary = $this->profit->summary();

        return view('reports.profit', [
            'revenue' => $summary['revenue'],
            'costOfMaterials' => $summary['cost_of_materials'],
            'roomCosts' => $summary['room_costs'],
            'cancelledRoomCosts' => $summary['cancelled_room_costs'],
            'adminExpenses' => $summary['admin_expenses'],
            'netProfit' => $summary['net_profit'],
            'workInProgress' => $summary['work_in_progress'],
            'stockValue' => $summary['stock_value'],
            'cashboxBalance' => $this->cashbox->summary()['balance'],
            'season' => $season,
            'seasonName' => $this->seasons->displayName($season),
            'isClosedSeason' => false,
            'seasonOptions' => $seasonOptions,
            'lossCarriedIn' => (int) $season->getRawOriginal('loss_carried_in'),
            'distributableProfit' => $this->profit->distributableProfit(),
            'adminByMonth' => $this->profit->adminExpensesByMonth(),
            'laborByMonth' => $this->profit->laborCostsByMonth(),
            'otherByMonth' => $this->profit->otherRoomCostsByMonth(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotData(Season $closed, $seasonOptions): array
    {
        return [
            'revenue' => (int) $closed->getRawOriginal('revenue'),
            'costOfMaterials' => (int) $closed->getRawOriginal('cost_of_materials'),
            'roomCosts' => (int) $closed->getRawOriginal('room_costs'),
            'cancelledRoomCosts' => (int) $closed->getRawOriginal('cancelled_room_costs'),
            'adminExpenses' => (int) $closed->getRawOriginal('admin_expenses'),
            'netProfit' => (int) $closed->getRawOriginal('net_profit'),
            'workInProgress' => (int) $closed->getRawOriginal('wip_carried_forward'),
            'stockValue' => (int) $closed->getRawOriginal('stock_value_at_close'),
            'cashboxBalance' => (int) $closed->getRawOriginal('cashbox_balance_at_close'),
            'season' => $closed,
            'seasonName' => $this->seasons->displayName($closed),
            'isClosedSeason' => true,
            'seasonOptions' => $seasonOptions,
            'lossCarriedIn' => (int) $closed->getRawOriginal('loss_carried_in'),
            'distributableProfit' => (int) $closed->getRawOriginal('distributable_profit'),
            'adminByMonth' => null,
            'laborByMonth' => null,
            'otherByMonth' => null,
        ];
    }
}
