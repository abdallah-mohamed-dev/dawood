{{--
    Purely presentational: maps a RoomStatus value to a colored pill. No
    business logic — the color mapping lives here in the view layer only.
--}}
@props(['status'])

@php
    $styles = match ($status->value) {
        'draft' => 'bg-gray-100 text-gray-700',
        'in_progress' => 'bg-warning/15 text-warning',
        'completed' => 'bg-success/15 text-success',
        'cancelled' => 'bg-danger/15 text-danger',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold $styles"]) }}>
    {{ $status->label() }}
</span>
