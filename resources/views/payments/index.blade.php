<x-app-layout title="مدفوعات العملاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">مدفوعات العملاء</h1>

    <x-data-table :headings="['التاريخ', 'العميل', 'الغرفة', 'ملاحظة', 'المبلغ', __('Actions')]" :rows="$payments">
        @foreach ($payments as $payment)
            <tr>
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
