<x-app-layout title="المصروفات الإدارية">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المصروفات الإدارية</h1>
        <a href="{{ route('expenses.categories.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
            البنود
        </a>
    </div>

    <x-quick-add :action="route('expenses.store')" title="تسجيل مصروف">
        <div>
            <label for="expense_category_id" class="mb-1 block text-xs font-medium text-gray-700">البند</label>
            <select id="expense_category_id" name="expense_category_id" required class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">اختر بندًا</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('expense_category_id') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-quick-field name="amount" label="المبلغ (ج.م)" type="number" step="0.01" min="0" width="w-32" required />
        <x-quick-field name="occurred_at" label="التاريخ" type="date" width="w-40" :value="old('occurred_at', now()->toDateString())" required />
        <x-quick-field name="description" label="الوصف" width="w-56" />

        <x-payment-method-select />
    </x-quick-add>

    @php
        $expenseHeadings = ['التاريخ', 'البند', 'الوصف', 'المبلغ', __('Actions')];
        $currentMonthKey = null;
    @endphp

    <x-data-table :headings="$expenseHeadings" :rows="$expenses">
        @foreach ($expenses as $expense)
            @php $rowMonthKey = $expense->occurred_at->format('Y-m'); @endphp

            {{--
                One separator row per calendar month change. The table is
                ordered by date descending, so a simple "did the month change
                since the last row" check is enough — no JS, no DB change.
                The total comes from $monthlyTotals (a query over ALL
                expenses, computed in the controller), not from summing the
                rows rendered here, so a month split across two pages still
                shows its true total on each page.
            --}}
            @if ($rowMonthKey !== $currentMonthKey)
                @php $currentMonthKey = $rowMonthKey; @endphp
                <tr class="bg-bg-subtle">
                    <td colspan="{{ count($expenseHeadings) }}" class="px-4 py-2 text-xs font-semibold text-secondary">
                        {{ __('date.months.'.$expense->occurred_at->month) }} {{ $expense->occurred_at->year }}
                        — إجمالي الشهر: <x-money :amount="(int) ($monthlyTotals[$rowMonthKey] ?? 0)" />
                    </td>
                </tr>
            @endif

            <tr>
                <td class="px-4 py-2">{{ $expense->occurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2">{{ $expense->category->name }}</td>
                <td class="px-4 py-2 text-secondary">{{ $expense->description ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$expense->amount" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('expenses.edit', $expense) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('expenses.destroy', $expense)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $expenses->links() }}
    </div>
</x-app-layout>
