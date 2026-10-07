{{--
    Accepts either a raw scaled integer (piastres, e.g. 54000) or the decimal
    string produced by App\Casts\MoneyCast::get() (e.g. "540.00" or "-540.00").
    Both forms render identically — the conversion is delegated to MoneyCast
    itself so there is exactly one place that knows the scale/rounding rules.

    The currency sits in a lighter, smaller span so the figure reads first.
    The space before it stays OUTSIDE that span, so stripping the tags still
    yields "85,000.00 ج.م" — which is what assertSeeText() in the tests reads.
--}}
@props(['amount'])

@php
    $piastres = is_string($amount) && str_contains($amount, '.')
        ? \App\Casts\MoneyCast::toScaledInt($amount)
        : (int) $amount;
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Casts\MoneyCast::toDisplayString($piastres) }} <span class="currency text-secondary">ج.م</span></span>
