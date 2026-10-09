@php
    $wasSubmitted = $errors->any() && old('amount') !== null;
@endphp

<x-panel
    id="payments-section"
    title="دفعات العميل"
    x-data="{ formOpen: {{ $wasSubmitted ? 'true' : 'false' }} }"
    x-on:open-payment-form.window="formOpen = true"
>
    <x-slot:actions>
        <button type="button" @click="formOpen = ! formOpen" class="rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink-soft transition-colors hover:bg-bg-subtle hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/40">+ تسجيل دفعة</button>
    </x-slot:actions>

    <div x-show="formOpen" x-cloak class="mb-4 rounded-lg border border-border-soft bg-bg-subtle p-3">
        <form method="POST" action="{{ route('rooms.payments.store', $room) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="amount" class="mb-1 block text-xs font-medium text-ink-soft">المبلغ (ج.م)</label>
                <input id="amount" type="number" step="1" min="0" name="amount" value="{{ old('amount') }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('amount')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="paid_at" class="mb-1 block text-xs font-medium text-ink-soft">التاريخ</label>
                <input id="paid_at" type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('paid_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="note" class="mb-1 block text-xs font-medium text-ink-soft">ملاحظة</label>
                <input id="note" type="text" name="note" value="{{ old('note') }}" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
            <x-payment-method-select id="payment_payment_method" />
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
            <button type="button" @click="formOpen = false" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">{{ __('Cancel') }}</button>
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
</x-panel>
