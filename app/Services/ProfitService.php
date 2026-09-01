<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Models\Expense;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;

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
        return (int) Room::query()->whereIn('status', $this->profitStatuses())->sum('sale_price');
    }

    public function costOfMaterials(): int
    {
        return (int) RoomMaterial::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->profitStatuses()))
            ->sum('cost');
    }

    public function roomCosts(): int
    {
        return (int) RoomCost::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->profitStatuses()))
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
            ->whereHas('room', fn ($query) => $query->where('status', RoomStatus::Cancelled))
            ->sum('amount');
    }

    public function adminExpenses(): int
    {
        return (int) Expense::query()->sum('amount');
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
