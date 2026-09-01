<x-app-layout title="المخزون">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المخزون</h1>
        <a href="{{ route('inventory.materials.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    <form method="GET" action="{{ route('inventory.materials.index') }}" class="mb-4 flex flex-wrap items-center gap-2">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="ابحث باسم المادة"
            class="w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
        >
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Search') }}
        </button>
        @if ($search !== '')
            <a href="{{ route('inventory.materials.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                إلغاء البحث
            </a>
        @endif
    </form>

    <x-data-table
        :headings="['اسم المادة', 'وحدة القياس', 'الكمية الحالية', __('Actions')]"
        :rows="$materials"
        :empty="$search !== '' ? 'لا توجد مادة بهذا الاسم.' : null"
    >
        @foreach ($materials as $material)
            <tr>
                <td class="px-4 py-2">{{ $material->name }}</td>
                <td class="px-4 py-2">{{ $material->unit }}</td>
                <td class="px-4 py-2">
                    <x-quantity :amount="(int) ($stockByMaterial[$material->id] ?? 0)" :unit="$material->unit" />
                </td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('inventory.materials.edit', $material) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('inventory.materials.destroy', $material)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $materials->links() }}
    </div>
</x-app-layout>
