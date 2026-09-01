<x-app-layout title="بنود المصروفات">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">بنود المصروفات</h1>

    <x-quick-add :action="route('expenses.categories.store')" title="إضافة بند">
        <x-quick-field name="name" label="اسم البند" width="w-64" required />
    </x-quick-add>

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

    <div class="mt-4">
        {{ $categories->links() }}
    </div>
</x-app-layout>
