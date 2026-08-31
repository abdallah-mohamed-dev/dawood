<x-app-layout title="المواد">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المواد</h1>
        <a href="{{ route('inventory.materials.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    <x-data-table :headings="['اسم المادة', 'وحدة القياس', 'الكمية الحالية', __('Actions')]" :rows="$materials">
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
</x-app-layout>
