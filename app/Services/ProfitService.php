<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Models\Expense;
use App\Models\Room;
use App\Models\RoomMaterial;

/**
 * Accrual-basis profit — deliberately separate from CashboxService's
 * cash-basis balance. See docs/profit-calculation.md: revenue and materials
 * cost are recognised only for `completed` rooms (matching principle);
 * admin expenses are period costs charged in full regardless of room status.
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

    public function adminExpenses(): int
    {
        return (int) Expense::query()->sum('amount');
    }

    public function netProfit(): int
    {
        return $this->revenue() - $this->costOfMaterials() - $this->adminExpenses();
    }

    public function workInProgress(): int
    {
        return (int) RoomMaterial::query()
            ->whereHas('room', fn ($query) => $query->whereIn('status', $this->workInProgressStatuses()))
            ->sum('cost');
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
     * @return array{revenue: int, cost_of_materials: int, admin_expenses: int, net_profit: int, work_in_progress: int, stock_value: int}
     */
    public function summary(): array
    {
        $revenue = $this->revenue();
        $costOfMaterials = $this->costOfMaterials();
        $adminExpenses = $this->adminExpenses();

        return [
            'revenue' => $revenue,
            'cost_of_materials' => $costOfMaterials,
            'admin_expenses' => $adminExpenses,
            'net_profit' => $revenue - $costOfMaterials - $adminExpenses,
            'work_in_progress' => $this->workInProgress(),
            'stock_value' => $this->stockValue(),
        ];
    }
}
