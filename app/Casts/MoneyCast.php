<?php

namespace App\Casts;

use App\Casts\Concerns\ScaledIntegerCast;

/**
 * Stores EGP amounts as a whole number of pounds. The workshop never deals in
 * anything smaller than a pound, so money carries no fraction at all: scale 1,
 * no decimals, and validationPattern() refuses any input with a decimal point.
 *
 * It still extends ScaledIntegerCast (scale 1 = "no scaling") so money keeps
 * going through the one conversion boundary every amount in the system shares
 * — the float rejection, the magnitude guard and the display formatting all
 * stay in one place.
 */
class MoneyCast extends ScaledIntegerCast
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
