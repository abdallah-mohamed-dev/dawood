<div class="mb-6 mt-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة دفعة</h2>
    <form method="POST" action="{{ route('rooms.payments.store', $room) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label for="amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
            <input id="amount" type="number" step="0.01" min="0" name="amount" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
            @error('amount')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="paid_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
            <input id="paid_at" type="date" name="paid_at" value="{{ now()->toDateString() }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
            @error('paid_at')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="note" class="mb-1 block text-xs font-medium text-gray-700">ملاحظة</label>
            <input id="note" type="text" name="note" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <x-payment-method-select id="payment_payment_method" />
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
    </form>
</div>

<x-data-table :headings="['رقم الإيصال', 'التاريخ', 'ملاحظة', 'المبلغ', __('Actions')]" :rows="$room->customerPayments">
    @foreach ($room->customerPayments as $payment)
        <tr>
            <td class="px-4 py-2 font-mono">{{ $payment->formattedReceiptNumber() }}</td>
            <td class="px-4 py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
            <td class="px-4 py-2 text-secondary">{{ $payment->note ?? '—' }}</td>
            <td class="px-4 py-2"><x-money :amount="$payment->amount" /></td>
            <td class="px-4 py-2 text-end">
                <a href="{{ route('payments.edit', $payment) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                <x-delete-button :action="route('payments.destroy', $payment)" class="ms-3" />
            </td>
        </tr>
    @endforeach
</x-data-table>
