{{-- One payment's inline edit row — shared by the payments page and the room
     page. The same three fields the old edit page offered; the receipt number
     never changes. Expects $payment, $reopenId and $colspan. --}}
@php
    $reopen = $reopenId === $payment->id;
    $bag = $reopen ? 'row' : 'none';
@endphp

<x-edit-row :action="route('payments.update', $payment)" :key="$payment->id" :colspan="$colspan">
    <x-quick-field name="amount" :id="'payment_amount_'.$payment->id" label="المبلغ (ج.م)" type="number" step="1" min="0" width="w-32" :bag="$bag" :value="$reopen ? old('amount', '') : $payment->amount" required />
    <x-payment-method-select :id="'payment_method_'.$payment->id" :bag="$bag" :selected="$payment->cashboxTransaction?->payment_method" />
    <x-quick-field name="note" :id="'payment_note_'.$payment->id" label="ملاحظة" width="w-56" :bag="$bag" :value="$reopen ? old('note', '') : ($payment->note ?? '')" />
</x-edit-row>
