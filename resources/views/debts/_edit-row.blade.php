{{-- One debt's inline edit row — shared by the outstanding and paid tables. --}}
@php
    $reopen = $reopenId === $debt->id;
    $bag = $reopen ? 'row' : 'none';
@endphp

<x-edit-row :action="route('debts.update', $debt)" :key="$debt->id" colspan="6">
    <x-quick-field name="creditor" :id="'creditor_'.$debt->id" label="لمين" width="w-48" :bag="$bag" :value="$reopen ? old('creditor', '') : $debt->creditor" required />
    <x-quick-field name="amount" :id="'amount_'.$debt->id" label="المبلغ (ج.م)" type="number" step="1" min="0" width="w-32" :bag="$bag" :value="$reopen ? old('amount', '') : $debt->amount" required />
    <x-quick-field name="incurred_at" :id="'incurred_at_'.$debt->id" label="تاريخ الدين" type="date" width="w-40" :bag="$bag" :value="$reopen ? old('incurred_at', '') : $debt->incurred_at->toDateString()" required />
    <x-quick-field name="due_at" :id="'due_at_'.$debt->id" label="ميعاد السداد" type="date" width="w-40" :bag="$bag" :value="$reopen ? old('due_at', '') : ($debt->due_at?->toDateString() ?? '')" />
    <x-quick-field name="note" :id="'note_'.$debt->id" label="ملاحظة" width="w-56" :bag="$bag" :value="$reopen ? old('note', '') : ($debt->note ?? '')" />
</x-edit-row>
