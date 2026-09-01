<x-app-layout title="تعديل مصروف">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تعديل مصروف</h1>

    <p class="mb-4 text-sm text-secondary">
        البند: {{ $expense->category->name }} — التاريخ: {{ $expense->occurred_at->format('Y-m-d') }}
    </p>

    <form method="POST" action="{{ route('expenses.update', $expense) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <x-field name="amount" label="المبلغ (ج.م)" type="number" step="0.01" min="0" :value="old('amount', $expense->amount)" required autofocus />

        <x-payment-method-select :selected="$expense->cashboxTransaction?->payment_method" />

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('expenses.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
