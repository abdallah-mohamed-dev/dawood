<x-app-layout title="المخزن">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">المخزن</h1>

    <x-quick-add :action="route('inventory.materials.store')" title="إضافة مادة">
        <x-quick-field name="name" label="اسم المادة" width="w-56" required />
        <div>
            <label for="material_type_id" class="mb-1 block text-xs font-medium text-gray-700">نوع الخامة</label>
            <select id="material_type_id" name="material_type_id" required class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">اختر النوع</option>
                @foreach ($materialTypes as $materialType)
                    <option value="{{ $materialType->id }}" @selected((string) old('material_type_id') === (string) $materialType->id)>{{ $materialType->name }}</option>
                @endforeach
            </select>
            @error('material_type_id')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
        <x-quick-field name="unit" label="وحدة القياس" width="w-40" placeholder="مثال: لوح، متر، قطعة" required />
    </x-quick-add>

    <form method="GET" action="{{ route('inventory.materials.index') }}" class="mb-6 flex flex-wrap items-center gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
            </svg>
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="ابحث باسم المادة"
                class="w-full rounded-full border border-transparent bg-bg-subtle py-2 ps-9 pe-3 text-sm text-gray-900 transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Search') }}
        </button>
        @if ($search !== '')
            <a href="{{ route('inventory.materials.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">
                إلغاء البحث
            </a>
        @endif
    </form>

    <x-data-table
        :headings="['اسم المادة', 'النوع', 'وحدة القياس', 'الكمية الحالية', __('Actions')]"
        :rows="$materials"
        :empty="$search !== '' ? 'لا توجد مادة بهذا الاسم.' : null"
    >
        @foreach ($materials as $material)
            <tr>
                <td class="px-4 py-2">{{ $material->name }}</td>
                <td class="px-4 py-2">{{ $material->materialType?->name ?? '—' }}</td>
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
