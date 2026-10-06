@php
    $sale = $profit['sale_price'];
    $paid = $room->paidAmount();
    $percent = $sale > 0 ? min(100, (int) round($paid * 100 / $sale)) : 0;
@endphp

<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">سعر البيع</div>
        <div class="mt-1 text-xl font-bold text-ink"><x-money :amount="$sale" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">تكلفة الخامات</div>
        <div class="mt-1 text-xl font-bold text-ink"><x-money :amount="$profit['materials']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">مصنعية + أخرى</div>
        <div class="mt-1 text-xl font-bold text-ink"><x-money :amount="$profit['labor'] + $profit['other']" /></div>
        <p class="mt-1 text-xs text-secondary">مصنعية <x-money :amount="$profit['labor']" /> + أخرى <x-money :amount="$profit['other']" /></p>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">إجمالي التكلفة</div>
        <div class="mt-1 text-xl font-bold text-ink"><x-money :amount="$profit['total_cost']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">{{ $room->status->countsTowardProfit() ? 'الربح' : 'الربح المتوقع' }}</div>
        <div class="mt-1 text-xl font-bold {{ $profit['profit'] < 0 ? 'text-danger' : 'text-success' }}">
            <x-money :amount="$profit['profit']" />
        </div>
        <p class="mt-1 text-xs text-secondary">
            لا يشمل المصروفات الإدارية.
            @unless ($room->status->countsTowardProfit())
                الخامات غير المصروفة لم تُحسب بعد.
            @endunless
        </p>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">المتبقي للتحصيل</div>
        <div class="mt-1 text-xl font-bold text-danger"><x-money :amount="$room->remainingAmount()" /></div>
        <p class="mt-1 text-xs text-secondary">مدفوع <x-money :amount="$paid" /> ({{ $percent }}%)</p>
    </div>
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
