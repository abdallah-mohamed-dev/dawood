<x-app-layout title="الديون">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">الديون</h1>

    <x-quick-add :action="route('debts.store')" title="تسجيل دين">
        <x-quick-field name="creditor" label="لمين" width="w-48" required />
        <x-quick-field name="amount" label="المبلغ (ج.م)" type="number" step="1" min="0" width="w-32" required />
        <x-quick-field name="incurred_at" label="تاريخ الدين" type="date" width="w-40" :value="old('incurred_at', now()->toDateString())" required />
        <x-quick-field name="due_at" label="ميعاد السداد" type="date" width="w-40" />
        <x-quick-field name="note" label="ملاحظة" width="w-56" />
    </x-quick-add>

    {{--
        Overdue = past its due date and still outstanding. The red edge uses
        the border-danger token from app.css, never a hex value.
    --}}
    <h2 class="mb-3 text-sm font-semibold text-ink">ديون قائمة</h2>

    <x-data-table :headings="['لمين', 'تاريخ الدين', 'ميعاد السداد', 'المبلغ', 'ملاحظة', __('Actions')]" :rows="$outstanding" empty="لا توجد ديون قائمة.">
        @foreach ($outstanding as $debt)
            <tr>
                <td class="px-4 py-2 {{ $debt->isOverdue() ? 'border-s-4 border-danger' : '' }}">{{ $debt->creditor }}</td>
                <td class="px-4 py-2 whitespace-nowrap">{{ $debt->incurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2 whitespace-nowrap {{ $debt->isOverdue() ? 'text-danger font-semibold' : '' }}">{{ $debt->due_at?->format('Y-m-d') ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$debt->amount" /></td>
                <td class="px-4 py-2 text-secondary">{{ $debt->note ?? '—' }}</td>
                <td class="px-4 py-2 text-end whitespace-nowrap">
                    <form method="POST" action="{{ route('debts.toggle-paid', $debt) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-success hover:underline">تعليم كمسدَّد</button>
                    </form>
                    <a href="{{ route('debts.edit', $debt) }}" class="ms-3 text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('debts.destroy', $debt)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <p class="mt-2 text-sm text-secondary">إجمالي الديون القائمة: <span class="font-semibold text-ink"><x-money :amount="$outstandingTotal" /></span></p>

    <h2 class="mb-3 mt-10 text-sm font-semibold text-ink">ديون مسدَّدة</h2>

    <x-data-table :headings="['لمين', 'تاريخ الدين', 'تاريخ السداد', 'المبلغ', 'ملاحظة', __('Actions')]" :rows="$paid" empty="لا توجد ديون مسدَّدة.">
        @foreach ($paid as $debt)
            <tr>
                <td class="px-4 py-2">{{ $debt->creditor }}</td>
                <td class="px-4 py-2 whitespace-nowrap">{{ $debt->incurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2 whitespace-nowrap">{{ $debt->paid_at?->format('Y-m-d') ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$debt->amount" /></td>
                <td class="px-4 py-2 text-secondary">{{ $debt->note ?? '—' }}</td>
                <td class="px-4 py-2 text-end whitespace-nowrap">
                    <form method="POST" action="{{ route('debts.toggle-paid', $debt) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-secondary hover:underline">إلغاء السداد</button>
                    </form>
                    <a href="{{ route('debts.edit', $debt) }}" class="ms-3 text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('debts.destroy', $debt)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <p class="mt-2 text-sm text-secondary">إجمالي الديون المسدَّدة: <span class="font-semibold text-ink"><x-money :amount="$paidTotal" /></span></p>
</x-app-layout>
