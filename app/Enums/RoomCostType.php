<?php

namespace App\Enums;

/**
 * The two kinds of room cost that are neither materials nor revenue:
 * labour paid to carpenters, and any other cost booked against the room.
 * They share one table (room_costs) because they share one shape and one
 * set of rules — see docs/profit-calculation.md.
 */
enum RoomCostType: string
{
    case Labor = 'labor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Labor => 'مصنعية',
            self::Other => 'مصروف إضافي',
        };
    }

    public function cashboxKind(): CashboxTransactionKind
    {
        return match ($this) {
            self::Labor => CashboxTransactionKind::RoomLabor,
            self::Other => CashboxTransactionKind::RoomExpense,
        };
    }
}
