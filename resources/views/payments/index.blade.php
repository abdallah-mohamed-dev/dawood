<x-app-layout title="مدفوعات العملاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">مدفوعات العملاء</h1>

    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-bg-subtle">
                <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">التاريخ</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">العميل</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الغرفة</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">ملاحظة</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المبلغ</th>
                    <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border [&>tr:hover]:bg-bg-subtle">
                @forelse ($payments as $payment)
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
                            <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $payments->links() }}
    </div>
</x-app-layout>
