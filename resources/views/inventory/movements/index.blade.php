<x-app-layout title="سجل المخزن">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">سجل المخزن</h1>

    <form method="GET" action="{{ route('inventory.movements.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
            </svg>
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="ابحث باسم المادة"
                class="w-full rounded-full border border-transparent bg-bg-subtle py-2 ps-9 pe-3 text-sm text-gray-900 transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>

        <select
            name="type"
            class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-gray-900 transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
        >
            <option value="">كل الأنواع</option>
            @foreach ($movementTypes as $movementType)
                <option value="{{ $movementType->value }}" @selected($selectedType === $movementType->value)>{{ $movementType->label() }}</option>
            @endforeach
        </select>

        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-gray-700">من تاريخ</label>
            <input
                id="from"
                type="date"
                name="from"
                value="{{ $from }}"
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>

        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-gray-700">إلى تاريخ</label>
            <input
                id="to"
                type="date"
                name="to"
                value="{{ $to }}"
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>

        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Search') }}
        </button>

        @if ($search !== '' || $selectedType !== '' || $from !== '' || $to !== '')
            <a href="{{ route('inventory.movements.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">
                إلغاء الفلترة
            </a>
        @endif
    </form>

    @php
        $movementHeadings = ['التاريخ', 'المادة', 'نوع الحركة', 'الكمية', 'التكلفة', 'الجهة'];
        $filtering = $search !== '' || $selectedType !== '' || $from !== '' || $to !== '';
        $currentMonthKey = null;
    @endphp

    <x-data-table :headings="$movementHeadings" :rows="$movements" :empty="$filtering ? 'لا توجد حركة مطابقة.' : null">
        @foreach ($movements as $movement)
            @php $rowMonthKey = $movement->occurred_at->format('Y-m'); @endphp

            {{--
                One separator row per calendar month change — the same pattern
                as resources/views/expenses/index.blade.php, down to the totals
                coming from a query over every matching row instead of from the
                rows rendered on this page.
            --}}
            @if ($rowMonthKey !== $currentMonthKey)
                @php $currentMonthKey = $rowMonthKey; @endphp
                <tr class="bg-bg-subtle">
                    <td colspan="{{ count($movementHeadings) }}" class="px-4 py-2 text-xs font-semibold text-secondary">
                        {{ __('date.months.'.$movement->occurred_at->month) }} {{ $movement->occurred_at->year }}
                        — إجمالي الشهر: <x-money :amount="(int) ($monthlyTotals[$rowMonthKey] ?? 0)" />
                    </td>
                </tr>
            @endif

            <tr>
                <td class="px-4 py-2">{{ $movement->occurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2">{{ $movement->material?->name ?? '—' }}</td>
                <td class="px-4 py-2">{{ $movement->type->label() }}</td>
                <td class="px-4 py-2"><x-quantity :amount="$movement->quantity" /></td>
                <td class="px-4 py-2"><x-money :amount="$movement->cost" /></td>
                {{-- `in` and `sold` movements have no room behind them. --}}
                <td class="px-4 py-2">{{ $movement->related?->room?->room_type ?? '—' }}</td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $movements->links() }}
    </div>
</x-app-layout>
