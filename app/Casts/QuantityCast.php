<?php

namespace App\Casts;

use App\Casts\Concerns\ScaledIntegerCast;

/**
 * Stores quantities as an integer number of thousandths (quantity × 1000).
 */
class QuantityCast extends ScaledIntegerCast
{
    protected static function scale(): int
    {
        return 1000;
    }

    protected static function decimals(): int
    {
        return 3;
    }
}
