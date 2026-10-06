@php
    $hasIssuedMaterials = $room->hasIssuedMaterials();
@endphp

<x-app-layout title="{{ $room->room_type }}">
    @include('rooms._header')
    @include('rooms._summary-cards')

    @php
        $costByType = $room->materialsCostByType();
        $typeBars = $materialTypes->map(fn ($type) => [
            'label' => $type->name,
            'value' => $costByType[$type->id] ?? 0,
            'note' => '',
        ])->all();
    @endphp
    <x-panel title="تكلفة الخامات حسب النوع" class="mb-6">
        <x-charts.hbar :items="$typeBars" alt="تكلفة الخامات حسب النوع" />
    </x-panel>

    @include('rooms._materials-section')
    <div class="mb-6 mt-6 grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        @include('rooms._costs-section', [
            'room' => $room,
            'type' => \App\Enums\RoomCostType::Labor,
            'title' => 'المصنعية',
            'descriptionLabel' => 'الوصف',
            'emptyMessage' => 'لا توجد دفعات مصنعية لهذه الغرفة.',
        ])

        @include('rooms._costs-section', [
            'room' => $room,
            'type' => \App\Enums\RoomCostType::Other,
            'title' => 'مصروفات إضافية',
            'descriptionLabel' => 'السبب',
            'emptyMessage' => 'لا توجد مصروفات إضافية لهذه الغرفة.',
        ])
    </div>

    @include('rooms._payments-section')

    @include('rooms._pricing-section')

    @include('rooms._activity-section')
</x-app-layout>
