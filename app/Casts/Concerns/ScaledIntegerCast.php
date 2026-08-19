<?php

namespace App\Casts\Concerns;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores a decimal quantity as a scaled integer (e.g. piastres instead of EGP,
 * thousandths instead of a fractional quantity) to avoid SQLite's lack of a
 * real DECIMAL type. All arithmetic on the underlying attribute must stay on
 * the raw scaled integer — this cast only converts at the read/write boundary.
 *
 * The conversion helpers are static so views/services can reuse the exact
 * same rounding and formatting rules without going through a Model instance
 * (e.g. the <x-money> component).
 *
 * @implements CastsAttributes<string, int|string>
 */
abstract class ScaledIntegerCast implements CastsAttributes
{
    abstract protected static function scale(): int;

    abstract protected static function decimals(): int;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return static::toDecimalString((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException(
                static::class.' does not accept float values — pass an integer already in the scaled unit, or a decimal string.'
            );
        }

        if (is_int($value)) {
            return $value;
        }

        return static::toScaledInt((string) $value);
    }

    /**
     * The regex pattern for validating user input before it reaches
     * toScaledInt() — e.g. `'quantity' => ['required', 'regex:'.QuantityCast::validationPattern()]`.
     * Kept here so every form using a scaled money/quantity field derives its
     * precision from the same place the cast itself uses, instead of each
     * FormRequest hand-typing "1,2" / "1,3" independently.
     */
    public static function validationPattern(): string
    {
        return '/^\d+(\.\d{1,'.static::decimals().'})?$/';
    }

    public static function toDecimalString(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);

        $whole = intdiv($scaled, static::scale());
        $fraction = $scaled % static::scale();

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, static::decimals(), '0', STR_PAD_LEFT);
    }

    public static function toScaledInt(string $value): int
    {
        $value = trim($value);

        // Reject anything but a plain decimal (no scientific notation, hex,
        // thousands separators, etc.) — PHP's (int) cast silently truncates
        // at the first unexpected character (e.g. (int) "1e10" === 1), which
        // would otherwise corrupt a financial amount instead of failing loudly.
        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(
                static::class." cannot parse \"{$value}\" as a plain decimal number."
            );
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;

        // 15 digits × scale (≤1000) stays well within PHP_INT_MAX (19 digits).
        // Without this, (int) on a longer string silently saturates instead
        // of erroring, and the subsequent × scale() silently overflows into
        // a float — exactly the failure mode this class exists to prevent.
        if (strlen($whole) > 15) {
            throw new InvalidArgumentException(
                static::class." magnitude too large to represent safely: \"{$value}\"."
            );
        }

        $decimals = static::decimals();

        // One extra digit is kept beyond the target precision to round half up.
        $fraction = str_pad(substr($fraction, 0, $decimals + 1), $decimals + 1, '0');

        $kept = (int) substr($fraction, 0, $decimals);
        $roundingDigit = (int) $fraction[$decimals];

        $scaled = ((int) $whole) * static::scale() + $kept;

        if ($roundingDigit >= 5) {
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }
}
