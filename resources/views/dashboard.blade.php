<x-app-layout title="لوحة التحكم">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">أهلاً، {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-secondary">الأرقام الأساسية للورشة دلوقتي.</p>
    </div>

    {{-- KPI cards: five numbers, two of them with a trend line. --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat label="رصيد الخزنة">
            <x-money :amount="$kpis['balance']" />
            <x-slot:after><x-charts.sparkline :values="array_column($series, 'balance_end')" /></x-slot:after>
        </x-stat>

        <x-stat label="صافي الربح (الموسم المفتوح)" hint="من الغرف المكتملة بعد المصروفات">
            <x-money :amount="$kpis['netProfit']" />
        </x-stat>

        <x-stat label="شغل تحت التنفيذ" hint="خامات اتصرفت لغرف لسه ماتكملتش">
            <x-money :amount="$kpis['workInProgress']" />
        </x-stat>

        <x-stat label="قيمة المخزن" hint="الكميات الموجودة × أسعارها">
            <x-money :amount="$kpis['stockValue']" />
        </x-stat>

        <x-stat label="داخل الخزنة الشهر ده">
            <x-money :amount="$kpis['inThisMonth']" />
            <x-slot:after><x-charts.sparkline :values="array_column($series, 'in')" /></x-slot:after>
        </x-stat>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-panel class="lg:col-span-2" title="رصيد الخزنة" sub="آخر 6 شهور — الرصيد في آخر كل شهر" link="{{ route('cashbox.index') }}" link-label="الخزنة">
            <x-charts.area-line
                :labels="array_column($series, 'label')"
                :values="array_column($series, 'balance_end')"
                alt="رصيد الخزنة في آخر 6 شهور: من {{ \App\Casts\MoneyCast::toDisplayString($series[0]['balance_end']) }} ج.م إلى {{ \App\Casts\MoneyCast::toDisplayString(end($series)['balance_end']) }} ج.م"
            />
        </x-panel>

        <x-panel title="داخل وخارج" sub="الخزنة شهر بشهر">
            <x-charts.grouped-bars
                :labels="array_column($series, 'label')"
                :series="[
                    ['name' => 'داخل', 'token' => 'chart-a', 'values' => array_column($series, 'in')],
                    ['name' => 'خارج', 'token' => 'chart-b', 'values' => array_column($series, 'out')],
                ]"
                alt="داخل وخارج الخزنة في آخر 6 شهور"
            />
        </x-panel>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-panel title="أكتر الخامات استهلاكًا" sub="حسب تكلفة الخامات المصروفة للغرف" class="lg:col-span-1">
            <x-charts.hbar :items="$topMaterials" alt="أكتر الخامات استهلاكًا" />
        </x-panel>

        <div class="lg:col-span-2">
            <x-panel title="الغرف تحت التنفيذ" sub="نسبة التحصيل من سعر البيع" link="{{ route('rooms.index') }}" link-label="كل الغرف">
                <x-data-table :headings="['الغرفة', 'العميل', 'سعر البيع', 'التحصيل']" :rows="$activeRooms" empty="مفيش غرف تحت التنفيذ دلوقتي.">
                    @foreach ($activeRooms as $room)
                        @php($sale = $room->getRawOriginal('sale_price'))
                        @php($paid = $room->paidAmount())
                        @php($percent = $sale > 0 ? min(100, (int) round($paid * 100 / $sale)) : 0)
                        <tr>
                            <td class="px-4 py-2.5 text-start"><a href="{{ route('rooms.show', $room) }}" class="font-medium text-ink hover:text-primary">{{ $room->room_type }}</a></td>
                            <td class="px-4 py-2.5 text-start text-ink-soft">{{ $room->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-end"><x-money :amount="$sale" /></td>
                            <td class="px-4 py-2.5 text-end">
                                <div class="ms-auto flex max-w-[11rem] items-center gap-2">
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-bg-subtle" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-success" style="width: {{ $percent }}%"></span>
                                    </span>
                                    <span class="w-10 text-xs tabular-nums text-secondary">{{ $percent }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </x-panel>
        </div>
    </div>
</x-app-layout>
