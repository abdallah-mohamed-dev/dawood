{{--
    A single figure on a card: a label, the number, and an optional line under
    it. The number itself is passed as the slot, so it can be <x-money>, a
    count, or a date without this component knowing the difference.

    tone/size are mapped from a fixed list rather than interpolated, so the
    final class names exist literally in this file for Tailwind to find.
--}}
@props(['label' => null, 'hint' => null, 'tone' => 'ink', 'size' => 'xl', 'after' => null])

@php
    $toneClass = match ($tone) {
        'success' => 'text-success',
        'danger' => 'text-danger',
        'warning' => 'text-warning',
        'primary' => 'text-primary',
        'secondary' => 'text-secondary',
        default => 'text-ink',
    };

    $sizeClass = match ($size) {
        'sm' => 'text-base',
        'lg' => 'text-lg',
        '2xl' => 'text-2xl',
        default => 'text-xl',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface p-4 shadow-sm']) }}>
    @if ($label)
        <div class="text-sm text-secondary">{{ $label }}</div>
    @endif

    <div class="mt-1 font-bold {{ $sizeClass }} {{ $toneClass }}">{{ $slot }}</div>

    @if ($hint)
        <p class="mt-3 text-xs text-secondary">{{ $hint }}</p>
    @endif

    {{-- Anything that belongs under the figure: a sparkline, a progress bar. --}}
    @if ($after)
        <div class="mt-3">{{ $after }}</div>
    @endif
</div>
