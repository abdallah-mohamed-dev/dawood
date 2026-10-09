@php
    $sale = $profit['sale_price'];
    $paid = $room->paidAmount();
    $percent = $sale > 0 ? min(100, (int) round($paid * 100 / $sale)) : 0;
@endphp

{{--
    The sidebar that used to be six cards across the top of the page. It is
    sticky on desktop and sits above the main column on mobile, with the
    exact same rows either way — nothing is dropped on a small screen.
--}}
<aside class="order-first space-y-3 lg:sticky lg:top-6 lg:order-last lg:h-max">
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-xs text-secondary">المتبقي للتحصيل</div>
        <div class="mt-1 text-2xl font-bold text-danger"><x-money :amount="$room->remainingAmount()" /></div>
        <div class="mt-3 h-1.5 rounded-full bg-bg-subtle">
            <div class="h-full rounded-full bg-success" style="width: {{ $percent }}%"></div>
        </div>
        <p class="mt-2 text-xs text-secondary">محصَّل {{ $percent }}٪ · <x-money :amount="$paid" /> من <x-money :amount="$sale" /></p>
    </div>

    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between py-1.5 text-sm">
            <span class="text-secondary">سعر البيع</span>
            <x-money :amount="$sale" class="font-semibold text-ink" />
        </div>
        <div class="flex items-center justify-between border-t border-border-soft py-1.5 text-sm">
            <span class="text-secondary">تكلفة الخامات</span>
            <x-money :amount="$profit['materials']" class="font-semibold text-ink" />
        </div>
        <div class="flex items-center justify-between border-t border-border-soft py-1.5 text-sm">
            <span class="text-secondary">مصنعية + أخرى</span>
            <x-money :amount="$profit['labor'] + $profit['other']" class="font-semibold text-ink" />
        </div>
        <div class="flex items-center justify-between border-t border-border-soft py-1.5 text-sm">
            <span class="text-secondary">إجمالي التكلفة</span>
            <x-money :amount="$profit['total_cost']" class="font-semibold text-ink" />
        </div>
        <div class="flex items-center justify-between border-t border-border-soft py-1.5 text-sm">
            <span class="text-secondary">{{ $room->status->countsTowardProfit() ? 'الربح' : 'الربح المتوقع' }}</span>
            <x-money :amount="$profit['profit']" class="font-semibold {{ $profit['profit'] < 0 ? 'text-danger' : 'text-success' }}" />
        </div>

        <div class="mt-3">
            <x-charts.composition-bar
                :parts="[
                    ['label' => 'تكلفة الخامات', 'value' => $profit['materials'], 'token' => 'ramp-1'],
                    ['label' => 'مصنعية + أخرى', 'value' => $profit['labor'] + $profit['other'], 'token' => 'ramp-2'],
                    ['label' => 'الربح', 'value' => max(0, $profit['profit']), 'token' => 'ramp-3'],
                ]"
                :total="$sale"
                alt="تركيب سعر البيع: تكلفة الخامات، مصنعية وأخرى، والربح"
            />
        </div>
    </div>

    <div class="space-y-2 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <a href="#materials-section" @click="$dispatch('open-materials-form')" class="block rounded-lg bg-primary px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark">صرف خامة</a>
        <a href="#payments-section" @click="$dispatch('open-payment-form')" class="block rounded-lg border border-border px-4 py-2 text-center text-sm text-ink-soft transition-colors hover:bg-bg-subtle">تسجيل دفعة</a>
        <a href="#costs-section" @click="$dispatch('open-cost-form')" class="block rounded-lg border border-border px-4 py-2 text-center text-sm text-ink-soft transition-colors hover:bg-bg-subtle">إضافة مصنعية</a>
    </div>
</aside>
