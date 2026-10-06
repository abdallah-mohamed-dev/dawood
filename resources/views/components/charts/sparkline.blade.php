{{--
    A small trend line for inside a KPI card. Values are integers in the
    scaled unit (piastres); the drawing is geometry only — no money math here.
    The number itself is printed by the card, so this line is decorative and
    stays out of the accessibility tree.
--}}
@props(['values' => [], 'token' => 'chart-a'])

@php
    $count = count($values);
    $min = $count ? min($values) : 0;
    $max = $count ? max($values) : 0;
    $span = max($max - $min, 1);
    $points = [];

    foreach ($values as $i => $value) {
        $x = $count > 1 ? round($i * 100 / ($count - 1), 2) : 50;
        $y = round(26 - (($value - $min) / $span) * 24, 2);
        $points[] = $x.','.$y;
    }
@endphp

@if ($count < 2 || ($max === 0 && $min === 0))
    <p class="text-xs text-secondary">مفيش بيانات كفاية</p>
@else
    <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="h-7 w-full overflow-visible" aria-hidden="true" focusable="false">
        <polyline points="{{ implode(' ', $points) }}" fill="none" class="stroke-{{ $token }}" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
    </svg>
@endif
