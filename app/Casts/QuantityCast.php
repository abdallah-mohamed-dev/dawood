<?php

namespace App\Casts;

use App\Casts\Concerns\ScaledIntegerCast;

/**
 * Stores quantities as whole units — a plank, a piece, a metre. The workshop
 * never counts a fraction of one, so a quantity carries no decimals at all:
 * scale 1, no decimals, and validationPattern() refuses any input with a
 * decimal point.
 *
 * It still extends ScaledIntegerCast (scale 1 = "no scaling") so quantities
 * keep going through the one conversion boundary every number in the system
 * shares — the float rejection, the magnitude guard and the display formatting
 * all stay in one place. Mirrors MoneyCast exactly, which is why
 * InventoryService::cost() is now a plain multiplication.
 */
class QuantityCast extends ScaledIntegerCast
{
    protected static function scale(): int
    {
        return 1;
    }

    protected static function decimals(): int
    {
        return 0;
    }
}
