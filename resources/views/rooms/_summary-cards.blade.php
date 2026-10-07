@php
    $sale = $profit['sale_price'];
    $paid = $room->paidAmount();
    $percent = $sale > 0 ? min(100, (int) round($paid * 100 / $sale)) : 0;
@endphp

<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <x-stat label="سعر البيع"><x-money :amount="$sale" /></x-stat>

    <x-stat label="تكلفة الخامات"><x-money :amount="$profit['materials']" /></x-stat>

    <x-stat label="مصنعية + أخرى">
        <x-money :amount="$profit['labor'] + $profit['other']" />
        <x-slot:hint>مصنعية <x-money :amount="$profit['labor']" /> + أخرى <x-money :amount="$profit['other']" /></x-slot:hint>
    </x-stat>

    <x-stat label="إجمالي التكلفة"><x-money :amount="$profit['total_cost']" /></x-stat>

    <x-stat
        :label="$room->status->countsTowardProfit() ? 'الربح' : 'الربح المتوقع'"
        :tone="$profit['profit'] < 0 ? 'danger' : 'success'"
    >
        <x-money :amount="$profit['profit']" />
        <x-slot:hint>
            لا يشمل المصروفات الإدارية.
            @unless ($room->status->countsTowardProfit())
                الخامات غير المصروفة لم تُحسب بعد.
            @endunless
        </x-slot:hint>
    </x-stat>

    <x-stat label="المتبقي للتحصيل" tone="danger">
        <x-money :amount="$room->remainingAmount()" />
        <x-slot:hint>مدفوع <x-money :amount="$paid" /> ({{ $percent }}%)</x-slot:hint>
    </x-stat>
</div>

<x-panel title="تركيب سعر البيع" sub="الخامات والمصنعية والمصروفات الأخرى والربح من إجمالي سعر البيع" class="mb-6">
    <x-charts.composition-bar
        :parts="[
            ['label' => 'تكلفة الخامات', 'value' => $profit['materials'], 'token' => 'ramp-1'],
            ['label' => 'مصنعية + أخرى', 'value' => $profit['labor'] + $profit['other'], 'token' => 'ramp-2'],
            ['label' => 'الربح', 'value' => max(0, $profit['profit']), 'token' => 'ramp-3'],
        ]"
        :total="$sale"
        alt="تركيب سعر البيع: تكلفة الخامات، مصنعية وأخرى، والربح"
    />
</x-panel>
