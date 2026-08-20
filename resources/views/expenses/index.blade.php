<x-app-layout title="المصروفات الإدارية">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">المصروفات الإدارية</h1>
        <div class="flex gap-3">
            <a href="{{ route('expenses.categories.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                البنود
            </a>
            <a href="{{ route('expenses.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Add') }}
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">التاريخ</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">البند</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">الوصف</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المبلغ</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-4 py-2">{{ $expense->occurred_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">{{ $expense->category->name }}</td>
                        <td class="px-4 py-2 text-secondary">{{ $expense->description ?? '—' }}</td>
                        <td class="px-4 py-2"><x-money :amount="$expense->amount" /></td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('expenses.edit', $expense) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $expenses->links() }}
    </div>
</x-app-layout>
