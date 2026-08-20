<x-app-layout title="تعديل مصروف">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">تعديل مصروف</h1>

    <p class="mb-4 text-sm text-secondary">
        البند: {{ $expense->category->name }} — التاريخ: {{ $expense->occurred_at->format('Y-m-d') }}
    </p>

    <form method="POST" action="{{ route('expenses.update', $expense) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">المبلغ (ج.م)</label>
            <input
                id="amount"
                type="number"
                step="0.01"
                min="0"
                name="amount"
                value="{{ old('amount', $expense->amount) }}"
                required
                autofocus
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('amount')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Save') }}
            </button>
            <a href="{{ route('expenses.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
