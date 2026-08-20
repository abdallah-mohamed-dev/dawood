<x-app-layout title="المصروفات الإدارية">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المصروفات الإدارية</h1>
        <div class="flex gap-3">
            <a href="{{ route('expenses.categories.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                البنود
            </a>
            <a href="{{ route('expenses.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Add') }}
            </a>
        </div>
    </div>

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
