<x-app-layout title="بنود المصروفات">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">بنود المصروفات</h1>
        <a href="{{ route('expenses.categories.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    <x-data-table :headings="['الاسم', 'عدد المصروفات', __('Actions')]" :rows="$categories">
        @foreach ($categories as $category)
            <tr>
                <td class="px-4 py-2">{{ $category->name }}</td>
                <td class="px-4 py-2">{{ $category->expenses_count }}</td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('expenses.categories.edit', $category) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('expenses.categories.destroy', $category)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
