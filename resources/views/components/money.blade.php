{{--
    Accepts a whole number of pounds, either as an int or as the string
    App\Casts\MoneyCast::get() hands back (e.g. "540" or "-540"). Money carries
    no fraction at all — see specs/022 — but a decimal string is still parsed
    through MoneyCast rather than cast directly, so there stays exactly one
    place that knows the rounding rules.

    The currency sits in a lighter, smaller span so the figure reads first.
    The space before it stays OUTSIDE that span, so stripping the tags still
    yields "85,000 ج.م" — which is what assertSeeText() in the tests reads.
--}}
@props(['amount'])

@php
    $pounds = is_string($amount) && str_contains($amount, '.')
        ? \App\Casts\MoneyCast::toScaledInt($amount)
        : (int) $amount;
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Casts\MoneyCast::toDisplayString($pounds) }} <span class="currency text-secondary">ج.م</span></span>
