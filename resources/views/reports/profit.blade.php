<x-app-layout title="تقرير الربح">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">تقرير الربح</h1>

    <form method="GET" action="{{ route('reports.profit') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-ink-soft shadow-sm">
        <span>
            @if ($isClosedSeason)
                الأرقام دي من السنابشوت المحفوظ لـ<strong class="text-ink">{{ $seasonName }}</strong> (مقفول)
            @else
                الأرقام دي للموسم المفتوح: <strong class="text-ink">{{ $seasonName }}</strong>
            @endif
        </span>
        <select name="season" onchange="this.form.submit()" class="rounded-full border border-transparent bg-bg-subtle px-4 py-1.5 text-sm text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="open" @selected(! $isClosedSeason)>الموسم المفتوح</option>
            @foreach ($seasonOptions as $option)
                <option value="{{ $option->id }}" @selected($isClosedSeason && $season->id === $option->id)>{{ 'موسم '.$option->number }} (مقفول)</option>
            @endforeach
        </select>
    </form>

    @if ($lossCarriedIn > 0)
        <div class="mb-6 rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-ink-soft">
            <div>خسارة مُدوَّرة من موسم سابق: <strong class="text-danger"><x-money :amount="$lossCarriedIn" /></strong></div>
            <div class="mt-1">القابل للتوزيع دلوقتي: <strong class="text-ink"><x-money :amount="$distributableProfit" /></strong> — الخسارة لازم تتغطى الأول.</div>
        </div>
    @endif

    <div class="mb-6 rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm text-ink-soft">
        <strong class="text-ink">رصيد الخزنة</strong> و<strong class="text-ink">صافي الربح</strong> رقمان مختلفان تمامًا ولا يجب الخلط بينهما:
        رصيد الخزنة نقدي (كل جنيه دخل أو خرج فعليًا)، بينما صافي الربح محاسبي (إيراد وتكلفة الغرف المكتملة فقط + كل المصروفات).
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">رصيد الخزنة (نقدي)</div>
            <div class="mt-1 text-2xl font-bold {{ $cashboxBalance < 0 ? 'text-danger' : 'text-ink' }}">
                <x-money :amount="$cashboxBalance" />
            </div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">صافي الربح (استحقاقي)</div>
            <div class="mt-1 text-2xl font-bold {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">
                <x-money :amount="$netProfit" />
            </div>
        </div>
    </div>

    <x-panel title="من الإيراد إلى صافي الربح" sub="الإيراد وكل خصم على التوالي، لحد صافي الربح النهائي">
        <x-charts.waterfall
            :steps="[
                ['label' => 'الإيراد', 'value' => $revenue, 'type' => 'total'],
                ['label' => 'تكلفة الخامات', 'value' => -$costOfMaterials, 'type' => 'change'],
                ['label' => 'تكاليف الغرف', 'value' => -$roomCosts, 'type' => 'change'],
                ['label' => 'غرف ملغاة', 'value' => -$cancelledRoomCosts, 'type' => 'change'],
                ['label' => 'مصروفات إدارية', 'value' => -$adminExpenses, 'type' => 'change'],
                ['label' => 'صافي الربح', 'value' => $netProfit, 'type' => 'total'],
            ]"
            alt="من الإيراد {{ \App\Casts\MoneyCast::toDisplayString($revenue) }} ج.م إلى صافي الربح {{ \App\Casts\MoneyCast::toDisplayString($netProfit) }} ج.م"
        />
    </x-panel>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">الإيراد (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-ink"><x-money :amount="$revenue" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكلفة الخامات (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-ink"><x-money :amount="$costOfMaterials" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف الغرف المكتملة (مصنعية + أخرى)</div>
            <div class="mt-1 text-lg font-semibold text-ink"><x-money :amount="$roomCosts" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف غرف ملغاة (خسارة)</div>
            <div class="mt-1 text-lg font-semibold {{ $cancelledRoomCosts > 0 ? 'text-danger' : 'text-ink' }}">
                <x-money :amount="$cancelledRoomCosts" />
            </div>
            <div class="mt-1 text-xs text-secondary">مصنعية ومصروفات غرف ألغيت — فلوس خرجت ولن تعود، فتُخصم فورًا.</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المصروفات الإدارية</div>
            <div class="mt-1 text-lg font-semibold text-ink"><x-money :amount="$adminExpenses" /></div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-panel title="أصول مش تكلفة" sub="خامات ومصروفات لم تُحسب بعد — موجودة لكن ليست ربحًا ولا خسارة">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between">
                    <span class="text-ink-soft">إنتاج تحت التشغيل (WIP)</span>
                    <span class="font-semibold text-ink"><x-money :amount="$workInProgress" /></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-ink-soft">قيمة المخزن غير المصروف</span>
                    <span class="font-semibold text-ink"><x-money :amount="$stockValue" /></span>
                </li>
            </ul>
            <p class="mt-3 text-xs text-secondary">خامات وتكاليف غرف لم تكتمل بعد، وخامات مشتراة ولم تُصرف — أصول، ليست تكلفة.</p>
        </x-panel>

        <x-panel title="نصيب الشركاء" sub="من الربح القابل للتوزيع في {{ $isClosedSeason ? 'الموسم المقفول' : 'الموسم المفتوح' }}" link="{{ route('partners.index') }}" link-label="الشركاء">
            <ul class="space-y-3 text-sm">
                @forelse ($partnerShares as $row)
                    <li class="flex items-center justify-between">
                        <span class="text-ink-soft">{{ $row['name'] }}</span>
                        <span class="font-semibold text-ink"><x-money :amount="$row['share']" /></span>
                    </li>
                @empty
                    <li class="text-secondary">مفيش شركاء مسجلين.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>

    @unless ($isClosedSeason)
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ([['المصروفات الإدارية شهر بشهر', $adminByMonth], ['المصنعيات شهر بشهر', $laborByMonth], ['مصروفات أخرى للغرف شهر بشهر', $otherByMonth]] as [$title, $byMonth])
                <x-panel :title="$title">
                    <x-data-table :headings="['الشهر', 'الإجمالي']" :rows="$byMonth" empty="مفيش حاجة في الموسم ده لسه.">
                        @foreach ($byMonth as $month => $total)
                            <tr>
                                <td class="px-4 py-2.5">{{ __('date.months.'.(int) substr($month, 5, 2)) }} {{ substr($month, 0, 4) }}</td>
                                <td class="px-4 py-2.5 text-end"><x-money :amount="$total" /></td>
                            </tr>
                        @endforeach
                        <x-slot:footer>
                            <tr>
                                <td class="px-4 py-2.5">الإجمالي</td>
                                <td class="px-4 py-2.5 text-end"><x-money :amount="$byMonth->sum()" /></td>
                            </tr>
                        </x-slot:footer>
                    </x-data-table>
                </x-panel>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-secondary">إجمالي المصنعيات + المصروفات الأخرى = كارت "تكاليف الغرف المكتملة" فوق. وإجمالي الجدول الأول = كارت "المصروفات الإدارية".</p>
    @endunless

    <div class="mt-6 text-sm">
        <a href="{{ route('reports.labor') }}" class="text-primary hover:underline">تفاصيل المصنعيات والبحث فيها</a>
    </div>
</x-app-layout>
