<x-app-layout title="بنود المصروفات">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">بنود المصروفات</h1>

    <x-quick-add :action="route('expenses.categories.store')" title="إضافة بند">
        <x-quick-field name="name" label="اسم البند" width="w-64" required />
    </x-quick-add>

    {{-- The row whose save just failed comes back open (see x-edit-row). --}}
    @php $reopenId = (int) old('editing_id', 0); @endphp

    <div x-data="{ editing: {{ $reopenId }} }">
        <x-data-table :headings="['الاسم', 'عدد المصروفات', __('Actions')]" :rows="$categories">
            @foreach ($categories as $category)
                @php $reopen = $reopenId === $category->id; @endphp
                <tr>
                    <td class="px-4 py-2">{{ $category->name }}</td>
                    <td class="px-4 py-2">{{ $category->expenses_count }}</td>
                    <td class="px-4 py-2 text-end whitespace-nowrap">
                        <button type="button" x-on:click="editing = {{ $category->id }}" class="text-primary hover:underline">{{ __('Edit') }}</button>
                        <x-delete-button :action="route('expenses.categories.destroy', $category)" class="ms-3" />
                    </td>
                </tr>
                <x-edit-row :action="route('expenses.categories.update', $category)" :key="$category->id" colspan="3">
                    <x-quick-field name="name" :id="'name_'.$category->id" label="اسم البند" width="w-64" :bag="$reopen ? 'row' : 'none'" :value="$reopen ? old('name', '') : $category->name" required />
                </x-edit-row>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $categories->links() }}
    </div>
</x-app-layout>
