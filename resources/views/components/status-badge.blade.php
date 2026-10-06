{{--
    Purely presentational: maps a RoomStatus value to a text tag with a colored
    dot. No business logic — the color mapping lives here in the view layer only.
    The text always carries the meaning, so the color is never the only signal.
--}}
@props(['status'])

@php
    [$tone, $dot] = match ($status->value) {
        'draft' => ['bg-bg-subtle text-ink-soft', 'bg-secondary'],
        'in_progress' => ['bg-warning/10 text-warning', 'bg-warning'],
        'completed' => ['bg-success/10 text-success', 'bg-success'],
        'cancelled' => ['bg-danger/10 text-danger', 'bg-danger'],
        default => ['bg-bg-subtle text-ink-soft', 'bg-secondary'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold $tone"]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $status->label() }}
</span>
