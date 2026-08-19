{{--
    Accepts either a raw scaled integer (piastres, e.g. 54000) or the decimal
    string produced by App\Casts\MoneyCast::get() (e.g. "540.00" or "-540.00").
    Both forms render identically — the conversion is delegated to MoneyCast
    itself so there is exactly one place that knows the scale/rounding rules.
--}}
@props(['amount'])

@php
    $piastres = is_string($amount) && str_contains($amount, '.')
        ? \App\Casts\MoneyCast::toScaledInt($amount)
        : (int) $amount;

    [$whole, $fraction] = explode('.', \App\Casts\MoneyCast::toDecimalString($piastres));
    $negative = str_starts_with($whole, '-');
    $whole = ltrim($whole, '-');
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $negative ? '-' : '' }}{{ number_format((int) $whole) }}.{{ $fraction }} ج.م</span>
