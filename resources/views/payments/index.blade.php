<x-app-layout title="مدفوعات العملاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">مدفوعات العملاء</h1>

    <form method="GET" action="{{ route('payments.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <input type="search" name="q" value="{{ $search }}" placeholder="ابحث بالعميل أو الغرفة أو رقم الإيصال" class="w-full rounded-full border border-transparent bg-bg-subtle py-2 px-4 text-sm text-gray-900 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">من تاريخ</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">إلى تاريخ</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">بحث</button>
        <a href="{{ route('payments.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">إلغاء الفلاتر</a>
    </form>


    <x-data-table :headings="['رقم الإيصال', 'التاريخ', 'العميل', 'الغرفة', 'ملاحظة', 'المبلغ', __('Actions')]" :rows="$payments">
        @php $currentMonth = null; @endphp
        @foreach ($payments as $payment)
            @php $month = $payment->paid_at->format('Y-m'); @endphp
            @if ($month !== $currentMonth)
                @php $currentMonth = $month; @endphp
                <tr class="bg-bg-subtle">
                    <td colspan="7" class="px-4 py-2 text-xs font-semibold text-secondary">
                        {{ __('date.months.'.$payment->paid_at->month) }} {{ $payment->paid_at->year }}
                        — إجمالي الشهر: <x-money :amount="(int) ($monthlyTotals[$month] ?? 0)" />
                    </td>
                </tr>
            @endif
            <tr>
                <td class="px-4 py-2 font-mono">{{ $payment->formattedReceiptNumber() }}</td>
                <td class="px-4 py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('customers.show', $payment->room->customer) }}" class="text-primary hover:underline">
                        {{ $payment->room->customer->name }}
                    </a>
                </td>
                <td class="px-4 py-2">
                    <a href="{{ route('rooms.show', $payment->room) }}" class="text-primary hover:underline">
                        {{ $payment->room->room_type }}
                    </a>
                </td>
                <td class="px-4 py-2 text-secondary">{{ $payment->note ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$payment->amount" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('payments.edit', $payment) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('payments.destroy', $payment)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $payments->links() }}
    </div>
</x-app-layout>
