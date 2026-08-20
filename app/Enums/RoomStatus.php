<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::InProgress => 'تحت التنفيذ',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    /**
     * Whether revenue/materials cost should be counted for this room in
     * profit calculations — see docs/profit-calculation.md.
     */
    public function countsTowardProfit(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Whether materials issued to a room in this status are work-in-progress
     * (an asset, not yet a cost) — see docs/profit-calculation.md. Neither
     * `completed` (already recognised as cost) nor `cancelled` (never
     * recognised) count.
     */
    public function countsTowardWorkInProgress(): bool
    {
        return ! $this->countsTowardProfit() && $this !== self::Cancelled;
    }
}
