<x-app-layout title="تعديل دين">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">تعديل دين</h1>

    <form method="POST" action="{{ route('debts.update', $debt) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <x-field name="creditor" label="لمين" :value="old('creditor', $debt->creditor)" required autofocus />
        <x-field name="amount" label="المبلغ (ج.م)" type="number" step="1" min="0" :value="old('amount', $debt->amount)" required />
        <x-field name="incurred_at" label="تاريخ الدين" type="date" :value="old('incurred_at', $debt->incurred_at->toDateString())" required />
        <x-field name="due_at" label="ميعاد السداد" type="date" :value="old('due_at', $debt->due_at?->toDateString())" />
        <x-field name="note" label="ملاحظة" :value="old('note', $debt->note)" />

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('debts.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
