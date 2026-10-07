<x-app-layout title="المصنعيات">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">المصنعيات</h1>

    <form method="GET" action="{{ route('reports.labor') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-soft">من تاريخ</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-soft">إلى تاريخ</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <select name="room_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل الغرف</option>
            @foreach ($roomOptions as $room)
                <option value="{{ $room->id }}" @selected($filters['room_id'] === $room->id)>{{ $room->room_type }}</option>
            @endforeach
        </select>
        <select name="customer_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل العملاء</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected($filters['customer_id'] === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <select name="season" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="open" @selected($filters['season'] === 'open')>الموسم المفتوح</option>
            @foreach ($seasons as $season)
                <option value="{{ $season->id }}" @selected($filters['season'] === (string) $season->id)>{{ 'موسم '.$season->number }} (مقفول)</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">بحث</button>
        <a href="{{ route('reports.labor') }}" class="text-sm text-secondary hover:text-danger hover:underline">إلغاء الفلاتر</a>
    </form>

    <x-stat label="إجمالي المصنعيات" size="2xl" class="mb-6"><x-money :amount="$total" /></x-stat>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-panel title="شهر بشهر">
            <x-data-table :headings="['الشهر', 'عدد الدفعات', 'الإجمالي']" :rows="$byMonth">
                @foreach ($byMonth as $row)
                    <tr>
                        <td class="px-4 py-2">{{ __('date.months.'.(int) substr($row->month, 5, 2)) }} {{ substr($row->month, 0, 4) }}</td>
                        <td class="px-4 py-2">{{ $row->payments }}</td>
                        <td class="px-4 py-2"><x-money :amount="(int) $row->total" /></td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-panel>

        <x-panel title="غرفة بغرفة">
            <x-data-table :headings="['الغرفة', 'العميل', 'عدد الدفعات', 'الإجمالي']" :rows="$perRoom">
                @foreach ($perRoom as $row)
                    @php $room = $rooms[$row->room_id] ?? null; @endphp
                    <tr>
                        <td class="px-4 py-2">
                            @if ($room)
                                <a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">{{ $room->room_type }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $room?->customer?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $row->payments }}</td>
                        <td class="px-4 py-2"><x-money :amount="(int) $row->total" /></td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-panel>
    </div>

    <x-panel title="كل الدفعات">
        <x-data-table :headings="['التاريخ', 'الغرفة', 'العميل', 'الوصف', 'المبلغ']" :rows="$details">
            @php $currentMonth = null; @endphp
            @foreach ($details as $detail)
                @php $month = $detail->occurred_at->format('Y-m'); @endphp
                @if ($month !== $currentMonth)
                    @php
                        $currentMonth = $month;
                        $monthRow = $byMonth->firstWhere('month', $month);
                    @endphp
                    <tr class="bg-bg-subtle">
                        <td colspan="5" class="px-4 py-2 text-xs font-semibold text-secondary">
                            {{ __('date.months.'.$detail->occurred_at->month) }} {{ $detail->occurred_at->year }}
                            — إجمالي الشهر: <x-money :amount="(int) ($monthRow->total ?? 0)" />
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="px-4 py-2">{{ $detail->occurred_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-2">{{ $detail->room?->room_type ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $detail->room?->customer?->name ?? '—' }}</td>
                    <td class="px-4 py-2 text-secondary">{{ $detail->description ?? '—' }}</td>
                    <td class="px-4 py-2"><x-money :amount="$detail->getRawOriginal('amount')" /></td>
                </tr>
            @endforeach
        </x-data-table>
        <div class="mt-4">{{ $details->links() }}</div>
    </x-panel>
</x-app-layout>
