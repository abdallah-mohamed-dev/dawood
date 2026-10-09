<x-app-layout title="الغرف">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">الغرف</h1>

    <form method="GET" action="{{ route('rooms.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
            </svg>
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] }}"
                placeholder="ابحث بنوع الغرفة أو اسم العميل"
                class="w-full rounded-full border border-transparent bg-bg-subtle py-2 ps-9 pe-3 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>

        <select name="status" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>

        <select name="customer_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل العملاء</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected($filters['customer_id'] === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>

        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-soft">من تاريخ</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-soft">إلى تاريخ</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <select name="season" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="open" @selected($filters['season'] === 'open')>الموسم المفتوح</option>
            <option value="all" @selected($filters['season'] === 'all')>كل المواسم</option>
            @foreach ($seasons as $season)
                <option value="{{ $season->id }}" @selected($filters['season'] === (string) $season->id)>{{ 'موسم '.$season->number }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">بحث</button>

        @if ($filtersActive)
            <a href="{{ route('rooms.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">إلغاء الفلاتر</a>
        @endif
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-panel title="سعر البيع مقابل المحصَّل" sub="حسب حالة الغرفة، للفلتر الحالي">
            <x-charts.grouped-bars
                :labels="collect($statuses)->map(fn ($s) => $s->label())->all()"
                :series="[
                    ['name' => 'سعر البيع', 'token' => 'chart-a', 'values' => collect($statuses)->map(fn ($s) => (int) ($byStatus[$s->value]->sale_total ?? 0))->all()],
                    ['name' => 'المحصَّل', 'token' => 'chart-b', 'values' => collect($statuses)->map(fn ($s) => $paidByStatus[$s->value] ?? 0)->all()],
                ]"
                alt="سعر البيع مقابل المحصَّل حسب حالة الغرفة"
            />
        </x-panel>
        <x-panel title="الغرف حسب الحالة" sub="عدد الغرف لكل حالة، للفلتر الحالي">
            <x-charts.hbar
                :items="collect($statuses)->map(fn ($s) => ['label' => $s->label(), 'value' => (int) ($byStatus[$s->value]->sale_total ?? 0), 'note' => ($byStatus[$s->value]->cnt ?? 0).' غرفة'])->all()"
                alt="الغرف حسب الحالة بقيمة سعر البيع"
            />
        </x-panel>
    </div>

    <x-data-table
        :headings="['الغرفة', 'العميل', 'الحالة', 'سعر البيع', 'المدفوع', 'المتبقي', 'التحصيل', 'تاريخ الإنشاء']"
        :rows="$rooms"
        :empty="$filtersActive ? 'مفيش نتايج للفلتر ده.' : 'مفيش غرف لسه.'"
    >
        @foreach ($rooms as $room)
            @php
                $sale = $room->getRawOriginal('sale_price');
                $paid = (int) ($room->paid_total ?? 0);
                $percent = $sale > 0 ? min(100, (int) round($paid * 100 / $sale)) : 0;
            @endphp
            <tr>
                <td class="px-4 py-2"><a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">{{ $room->room_type }}</a></td>
                <td class="px-4 py-2"><a href="{{ route('customers.show', $room->customer) }}" class="text-primary hover:underline">{{ $room->customer->name }}</a></td>
                <td class="px-4 py-2"><x-status-badge :status="$room->status" /></td>
                <td class="px-4 py-2"><x-money :amount="$sale" /></td>
                <td class="px-4 py-2"><x-money :amount="$paid" /></td>
                <td class="px-4 py-2"><x-money :amount="$sale - $paid" /></td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-16 overflow-hidden rounded-full bg-bg-subtle" aria-hidden="true">
                            <span class="block h-full rounded-full bg-success" style="width: {{ $percent }}%"></span>
                        </span>
                        <span class="w-9 text-xs tabular-nums text-secondary">{{ $percent }}%</span>
                    </div>
                </td>
                <td class="px-4 py-2">{{ $room->created_at->format('Y-m-d') }}</td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $rooms->links() }}
    </div>
</x-app-layout>
