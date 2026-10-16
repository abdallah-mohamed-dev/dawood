@php
    $hasIssuedMaterials = $room->hasIssuedMaterials();
@endphp

<x-app-layout title="{{ $room->room_type }}">
    @include('rooms._header')

    {{--
        grid-cols-1 (= minmax(0, 1fr)), not the bare implicit column: an
        implicit grid column is as wide as its widest content, so on a phone
        the materials table (≈650px) stretched the whole page sideways even
        though the table scrolls inside its own box. min-w-0 on the column
        is the same guard for the lg layout.
    --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="min-w-0 space-y-4">
            @include('rooms._materials-section')
            @include('rooms._costs-section')
            @include('rooms._payments-section')
            @include('rooms._pricing-section')
            @include('rooms._activity-section')
        </div>

        @include('rooms._rail')
    </div>
</x-app-layout>
