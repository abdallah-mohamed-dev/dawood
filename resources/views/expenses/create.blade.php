<x-app-layout title="إضافة مصروف">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">إضافة مصروف</h1>

    <form method="POST" action="{{ route('expenses.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="expense_category_id" class="mb-1 block text-sm font-medium text-gray-700">البند</label>
            <select
                id="expense_category_id"
                name="expense_category_id"
                required
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
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

        <div>
            <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">المبلغ (ج.م)</label>
            <input
                id="amount"
                type="number"
                step="0.01"
                min="0"
                name="amount"
                value="{{ old('amount') }}"
                required
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('amount')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="occurred_at" class="mb-1 block text-sm font-medium text-gray-700">التاريخ</label>
            <input
                id="occurred_at"
                type="date"
                name="occurred_at"
                value="{{ old('occurred_at', now()->toDateString()) }}"
                required
                class="rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('occurred_at')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="description" class="mb-1 block text-sm font-medium text-gray-700">الوصف</label>
            <input
                id="description"
                type="text"
                name="description"
                value="{{ old('description') }}"
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('description')
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
