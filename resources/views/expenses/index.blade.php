<x-app-layout title="المصروفات الإدارية">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المصروفات الإدارية</h1>
        <a href="{{ route('expenses.categories.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
            البنود
        </a>
    </div>

    <x-quick-add :action="route('expenses.store')" title="تسجيل مصروف">
        <div>
            <label for="expense_category_id" class="mb-1 block text-xs font-medium text-gray-700">البند</label>
            <select id="expense_category_id" name="expense_category_id" required class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">اختر بندًا</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('expense_category_id') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-quick-field name="amount" label="المبلغ (ج.م)" type="number" step="0.01" min="0" width="w-32" required />
        <x-quick-field name="occurred_at" label="التاريخ" type="date" width="w-40" :value="old('occurred_at', now()->toDateString())" required />
        <x-quick-field name="description" label="الوصف" width="w-56" />

        <x-payment-method-select />
    </x-quick-add>

    <x-data-table :headings="['التاريخ', 'البند', 'الوصف', 'المبلغ', __('Actions')]" :rows="$expenses">
        @foreach ($expenses as $expense)
            <tr>
                <td class="px-4 py-2">{{ $expense->occurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2">{{ $expense->category->name }}</td>
                <td class="px-4 py-2 text-secondary">{{ $expense->description ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$expense->amount" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('expenses.edit', $expense) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('expenses.destroy', $expense)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $expenses->links() }}
    </div>
</x-app-layout>
