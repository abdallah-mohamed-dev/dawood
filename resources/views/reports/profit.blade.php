<x-app-layout title="تقرير الربح">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تقرير الربح</h1>

    <form method="GET" action="{{ route('reports.profit') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-gray-700 shadow-sm">
        <span>
            @if ($isClosedSeason)
                الأرقام دي من السنابشوت المحفوظ لـ<strong class="text-gray-900">{{ $seasonName }}</strong> (مقفول)
            @else
                الأرقام دي للموسم المفتوح: <strong class="text-gray-900">{{ $seasonName }}</strong>
            @endif
        </span>
        <select name="season" onchange="this.form.submit()" class="rounded-full border border-transparent bg-bg-subtle px-4 py-1.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="open" @selected(! $isClosedSeason)>الموسم المفتوح</option>
            @foreach ($seasonOptions as $option)
                <option value="{{ $option->id }}" @selected($isClosedSeason && $season->id === $option->id)>{{ 'موسم '.$option->number }} (مقفول)</option>
            @endforeach
        </select>
    </form>

    @if ($lossCarriedIn > 0)
        <div class="mb-6 rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-gray-700">
            <div>خسارة مُدوَّرة من موسم سابق: <strong class="text-danger"><x-money :amount="$lossCarriedIn" /></strong></div>
            <div class="mt-1">القابل للتوزيع دلوقتي: <strong class="text-gray-900"><x-money :amount="$distributableProfit" /></strong> — الخسارة لازم تتغطى الأول.</div>
        </div>
    @endif

    <div class="mb-6 rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm text-gray-700">
        <strong class="text-gray-900">رصيد الخزنة</strong> و<strong class="text-gray-900">صافي الربح</strong> رقمان مختلفان تمامًا ولا يجب الخلط بينهما:
        رصيد الخزنة نقدي (كل جنيه دخل أو خرج فعليًا)، بينما صافي الربح محاسبي (إيراد وتكلفة الغرف المكتملة فقط + كل المصروفات).
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">رصيد الخزنة (نقدي)</div>
            <div class="mt-1 text-2xl font-bold {{ $cashboxBalance < 0 ? 'text-danger' : 'text-gray-900' }}">
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

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">الإيراد (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$revenue" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكلفة الخامات (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$costOfMaterials" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف الغرف المكتملة (مصنعية + أخرى)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$roomCosts" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف غرف ملغاة (خسارة)</div>
            <div class="mt-1 text-lg font-semibold {{ $cancelledRoomCosts > 0 ? 'text-danger' : 'text-gray-900' }}">
                <x-money :amount="$cancelledRoomCosts" />
            </div>
            <div class="mt-1 text-xs text-secondary">مصنعية ومصروفات غرف ألغيت — فلوس خرجت ولن تعود، فتُخصم فورًا.</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المصروفات الإدارية</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$adminExpenses" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إنتاج تحت التشغيل (WIP)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$workInProgress" /></div>
            <div class="mt-1 text-xs text-secondary">خامات وتكاليف غرف لم تكتمل بعد — أصل، ليست تكلفة.</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">قيمة المخزن غير المصروف</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$stockValue" /></div>
            <div class="mt-1 text-xs text-secondary">خامات مشتراة ولم تُصرف بعد — أصل، ليست تكلفة.</div>
        </div>
    </div>

    @unless ($isClosedSeason)
        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ([['المصروفات الإدارية شهر بشهر', $adminByMonth], ['المصنعيات شهر بشهر', $laborByMonth], ['مصروفات أخرى للغرف شهر بشهر', $otherByMonth]] as [$title, $byMonth])
                <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ $title }}</h2>
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-bg-subtle">
                            <tr>
                                <th class="px-3 py-2 text-start text-xs font-semibold text-secondary">الشهر</th>
                                <th class="px-3 py-2 text-end text-xs font-semibold text-secondary">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($byMonth as $month => $total)
                                <tr>
                                    <td class="px-3 py-2">{{ __('date.months.'.(int) substr($month, 5, 2)) }} {{ substr($month, 0, 4) }}</td>
                                    <td class="px-3 py-2 text-end"><x-money :amount="$total" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-3 py-4 text-center text-secondary">مفيش حاجة في الموسم ده لسه.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-bold">
                                <td class="px-3 py-2">الإجمالي</td>
                                <td class="px-3 py-2 text-end"><x-money :amount="$byMonth->sum()" /></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-secondary">إجمالي المصنعيات + المصروفات الأخرى = كارت "تكاليف الغرف المكتملة" فوق. وإجمالي الجدول الأول = كارت "المصروفات الإدارية".</p>
    @endunless

    <div class="mt-6 text-sm">
        <a href="{{ route('reports.labor') }}" class="text-primary hover:underline">تفاصيل المصنعيات والبحث فيها</a>
    </div>
</x-app-layout>
