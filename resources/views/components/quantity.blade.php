{{--
    Mirrors <x-money>: accepts either a raw integer of whole units (e.g. 14) or
    the string produced by App\Casts\QuantityCast::get() (e.g. "14"). Both
    forms render identically.

    The decimal branch below is a leftover safety net from before specs/023 made
    quantities whole: a stray "2.5" from anywhere still renders instead of being
    truncated to 2.
--}}
@props(['amount', 'unit' => null])

@php
    $scaled = is_string($amount) && str_contains($amount, '.')
        ? \App\Casts\QuantityCast::toScaledInt($amount)
        : (int) $amount;
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Casts\QuantityCast::toDisplayString($scaled) }}{{ $unit ? ' '.$unit : '' }}</span>
