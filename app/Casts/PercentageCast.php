<?php

namespace App\Casts;

use App\Casts\Concerns\ScaledIntegerCast;

/**
 * Stores a percentage as an integer number of basis points (percent × 100),
 * so 33.33% is 3333 and the partners' shares always add up to exactly 10,000.
 *
 * A percentage is not money: it is a rate, and it keeps two decimals even
 * though money (specs/022) no longer has any. It had been borrowing MoneyCast
 * for nothing but the shared ×100 scale, which tied the partners' precision to
 * a currency decision it has no stake in.
 */
class PercentageCast extends ScaledIntegerCast
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
