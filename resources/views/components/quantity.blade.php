{{--
    Mirrors <x-money>: accepts either a raw scaled integer (thousandths, e.g.
    2500) or the decimal string produced by App\Casts\QuantityCast::get()
    (e.g. "2.500"). Both forms render identically.
--}}
@props(['amount', 'unit' => null])

@php
    $scaled = is_string($amount) && str_contains($amount, '.')
        ? \App\Casts\QuantityCast::toScaledInt($amount)
        : (int) $amount;

    [$whole, $fraction] = explode('.', \App\Casts\QuantityCast::toDecimalString($scaled));
    $negative = str_starts_with($whole, '-');
    $whole = ltrim($whole, '-');
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $negative ? '-' : '' }}{{ number_format((int) $whole) }}.{{ $fraction }}{{ $unit ? ' '.$unit : '' }}</span>
