<?php

namespace App\Services;

use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Models\Expense;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Accrual-basis profit — deliberately separate from CashboxService's
 * cash-basis balance. See docs/profit-calculation.md: revenue, materials
 * cost and room costs are recognised only for `completed` rooms (matching
 * principle); admin expenses are period costs charged in full regardless
 * of room status.
 *
 * Cancelled rooms are the one asymmetry, and it is deliberate: their
 * materials go back to stock (or are written off with the room), but their
 * labour and extra costs are cash that is gone for good — so those are
 * charged straight to profit as a loss, and never sit in WIP.
 */
class ProfitService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function revenue(): int
    {
        return (int) Room::query()->whereIn('status', $this->profitStatuses())->whereNull('season_id')->sum('sale_price');
    }

    public function costOfMaterials(): int
    {
        return (int) RoomMaterial::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->profitStatuses())->whereNull('season_id'))
            ->sum('cost');
    }

    public function roomCosts(): int
    {
        return (int) RoomCost::query()
            ->whereNull('season_id')
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->profitStatuses())->whereNull('season_id'))
            ->sum('amount');
    }

    /**
     * Labour and extra costs of cancelled rooms: money that left the
     * cashbox and can never come back, so it hits profit immediately
     * instead of waiting for a completion that will never happen.
     */
    public function cancelledRoomCosts(): int
    {
        return (int) RoomCost::query()
            ->whereNull('season_id')
            ->whereHas('room', fn ($query) => $query->where('status', RoomStatus::Cancelled)->whereNull('season_id'))
            ->sum('amount');
    }

    public function adminExpenses(): int
    {
        return (int) Expense::query()->whereNull('season_id')->sum('amount');
    }

    /**
     * Admin expenses of the open season, grouped by "Y-m" (piastres). Pure read.
     *
     * @return Collection<string, int>
     */
    public function adminExpensesByMonth(): Collection
    {
        return $this->byMonth(Expense::query()->whereNull('season_id'), 'amount', 'occurred_at');
    }

    /**
     * Labor costs of rooms that count toward profit, grouped by "Y-m".
     *
     * @return Collection<string, int>
     */
    public function laborCostsByMonth(): Collection
    {
        return $this->byMonth($this->profitRoomCosts(RoomCostType::Labor), 'amount', 'occurred_at');
    }

    /**
     * Other room costs (extras) of rooms that count toward profit, grouped by "Y-m".
     *
     * @return Collection<string, int>
     */
    public function otherRoomCostsByMonth(): Collection
    {
        return $this->byMonth($this->profitRoomCosts(RoomCostType::Other), 'amount', 'occurred_at');
    }

    private function profitRoomCosts(RoomCostType $type): Builder
    {
        return RoomCost::query()
            ->where('type', $type)
            ->whereNull('season_id')
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->profitStatuses())->whereNull('season_id'));
    }

    /**
     * @return Collection<string, int>
     */
    private function byMonth(Builder $query, string $amountColumn, string $dateColumn): Collection
    {
        return $query
            ->selectRaw("strftime('%Y-%m', {$dateColumn}) as month, SUM({$amountColumn}) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('total', 'month')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * The part of this season's profit that can be paid out: what is left
     * after the rounded-over loss from earlier seasons is covered (specs/012
     * §3.4-أ). Zero while that loss is still bigger than the profit.
     */
    public function distributableProfit(): int
    {
        $lossCarriedIn = (int) Season::query()->where('status', SeasonStatus::Open)->value('loss_carried_in');

        return max(0, $this->netProfit() - $lossCarriedIn);
    }

    public function netProfit(): int
    {
        return $this->revenue()
            - $this->costOfMaterials()
            - $this->roomCosts()
            - $this->cancelledRoomCosts()
            - $this->adminExpenses();
    }

    public function workInProgress(): int
    {
        $materials = (int) RoomMaterial::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->workInProgressStatuses()))
            ->sum('cost');

        $costs = (int) RoomCost::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->workInProgressStatuses()))
            ->sum('amount');

        return $materials + $costs;
    }

    /**
     * One room's own economics, for the room page. Deliberately excludes
     * admin expenses — those are workshop-wide and cannot be attributed to
     * a single room, so mixing them in here would produce a number that
     * looks like profit but answers no real question.
     *
     * @return array{sale_price: int, materials: int, labor: int, other: int, total_cost: int, profit: int}
     */
    public function forRoom(Room $room): array
    {
        $salePrice = (int) $room->getRawOriginal('sale_price');
        $materials = $room->materialsCost();
        $labor = $room->laborCost();
        $other = $room->otherCost();
        $totalCost = $materials + $labor + $other;

        return [
            'sale_price' => $salePrice,
            'materials' => $materials,
            'labor' => $labor,
            'other' => $other,
            'total_cost' => $totalCost,
            'profit' => $salePrice - $totalCost,
        ];
    }

    /**
     * @return list<RoomStatus>
     */
    /**
     * Estimates against actuals for one room, per cost line. Pure read — it
     * never writes and never feeds netProfit() or the cashbox.
     *
     * @return array<string, array{estimated: ?int, actual: int, difference: ?int}>
     */
    public function pricingComparison(Room $room): array
    {
        $byType = $room->materialsCostByType();
        $woodId = MaterialType::query()->where('name', 'خامة')->value('id');
        $accessoryId = MaterialType::query()->where('name', 'اكسسوار')->value('id');

        $lines = [
            'materials' => ['estimated' => $room->getRawOriginal('estimated_materials'), 'actual' => $byType[$woodId] ?? 0],
            'accessories' => ['estimated' => $room->getRawOriginal('estimated_accessories'), 'actual' => $byType[$accessoryId] ?? 0],
            'labor' => ['estimated' => $room->getRawOriginal('estimated_labor'), 'actual' => $room->laborCost()],
            'other' => ['estimated' => $room->getRawOriginal('estimated_other'), 'actual' => $room->otherCost()],
        ];

        $lines = collect($lines)->map(fn (array $line) => [
            'estimated' => $line['estimated'] === null ? null : (int) $line['estimated'],
            'actual' => (int) $line['actual'],
            'difference' => $line['estimated'] === null ? null : (int) $line['actual'] - (int) $line['estimated'],
        ]);

        // The total is only compared when every line was estimated — a partial
        // estimate total would look over or under budget for the wrong reason.
        $complete = $lines->every(fn (array $line) => $line['estimated'] !== null);

        return $lines->all() + [
            'total' => [
                'estimated' => $complete ? $lines->sum('estimated') : null,
                'actual' => $lines->sum('actual'),
                'difference' => $complete ? $lines->sum('actual') - $lines->sum('estimated') : null,
            ],
        ];
    }

    /**
     * Expected versus actual duration in days. The clock runs from the first
     * time the room went in progress to the last time it was completed,
     * both days included.
     *
     * @return array{expected: ?int, actual: ?int, difference: ?int}
     */
    public function durationComparison(Room $room): array
    {
        $expected = $room->expected_duration_days;
        $actual = $room->started_at && $room->completed_at
            ? (int) $room->started_at->diffInDays($room->completed_at, false) + 1
            : null;

        return [
            'expected' => $expected,
            'actual' => $actual,
            'difference' => $expected !== null && $actual !== null ? $actual - $expected : null,
        ];
    }

    private function profitStatuses(): array
    {
        return array_values(array_filter(RoomStatus::cases(), fn (RoomStatus $status) => $status->countsTowardProfit()));
    }

    /**
     * @return list<RoomStatus>
     */
    private function workInProgressStatuses(): array
    {
        return array_values(array_filter(RoomStatus::cases(), fn (RoomStatus $status) => $status->countsTowardWorkInProgress()));
    }

    public function stockValue(): int
    {
        return $this->inventory->stockValue();
    }

    /**
     * Same numbers as calling each method individually, but each underlying
     * query runs once instead of revenue()/costOfMaterials()/adminExpenses()
     * being repeated inside netProfit() — for callers (like the profit
     * report page) that need every figure together on one page load.
     *
     * @return array{revenue: int, cost_of_materials: int, room_costs: int, cancelled_room_costs: int, admin_expenses: int, net_profit: int, work_in_progress: int, stock_value: int}
     */
    public function summary(): array
    {
        $revenue = $this->revenue();
        $costOfMaterials = $this->costOfMaterials();
        $roomCosts = $this->roomCosts();
        $cancelledRoomCosts = $this->cancelledRoomCosts();
        $adminExpenses = $this->adminExpenses();

        return [
            'revenue' => $revenue,
            'cost_of_materials' => $costOfMaterials,
            'room_costs' => $roomCosts,
            'cancelled_room_costs' => $cancelledRoomCosts,
            'admin_expenses' => $adminExpenses,
            'net_profit' => $revenue - $costOfMaterials - $roomCosts - $cancelledRoomCosts - $adminExpenses,
            'work_in_progress' => $this->workInProgress(),
            'stock_value' => $this->stockValue(),
        ];
    }
}
