<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case ReturnedToStock = 'return';

    public function label(): string
    {
        return match ($this) {
            self::In => 'وارد',
            self::Out => 'صادر',
            self::ReturnedToStock => 'مرتجع',
        };
    }
}
