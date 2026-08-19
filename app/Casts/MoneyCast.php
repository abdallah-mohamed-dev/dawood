<?php

namespace App\Casts;

use App\Casts\Concerns\ScaledIntegerCast;

/**
 * Stores EGP amounts as an integer number of piastres (amount × 100).
 */
class MoneyCast extends ScaledIntegerCast
{
    protected static function scale(): int
    {
        return 100;
    }

    protected static function decimals(): int
    {
        return 2;
    }
}
