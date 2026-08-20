<x-app-layout title="إضافة مصروف">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">إضافة مصروف</h1>

    <form method="POST" action="{{ route('expenses.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="expense_category_id" class="mb-1 block text-sm font-medium text-gray-700">البند</label>
            <select
                id="expense_category_id"
                name="expense_category_id"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
                <option value="">اختر بندًا</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('expense_category_id') === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('expense_category_id')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-field name="amount" label="المبلغ (ج.م)" type="number" step="0.01" min="0" :value="old('amount')" required />

        <div>
            <label for="occurred_at" class="mb-1 block text-sm font-medium text-gray-700">التاريخ</label>
            <input
                id="occurred_at"
                type="date"
                name="occurred_at"
                value="{{ old('occurred_at', now()->toDateString()) }}"
                required
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
            @error('occurred_at')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-field name="description" label="الوصف" :value="old('description')" />

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
