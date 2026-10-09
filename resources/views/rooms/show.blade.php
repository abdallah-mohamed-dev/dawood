@php
    $hasIssuedMaterials = $room->hasIssuedMaterials();
@endphp

<x-app-layout title="{{ $room->room_type }}">
    @include('rooms._header')

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="space-y-4">
            @include('rooms._materials-section')
            @include('rooms._costs-section')
            @include('rooms._payments-section')
            @include('rooms._pricing-section')
            @include('rooms._activity-section')
        </div>

        @include('rooms._rail')
    </div>
</x-app-layout>
