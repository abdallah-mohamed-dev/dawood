<x-app-layout title="بنود المصروفات">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">بنود المصروفات</h1>
        <a href="{{ route('expenses.categories.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('Add') }}
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">الاسم</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">عدد المصروفات</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-2">{{ $category->name }}</td>
                        <td class="px-4 py-2">{{ $category->expenses_count }}</td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('expenses.categories.edit', $category) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('expenses.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
