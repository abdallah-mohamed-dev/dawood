<x-app-layout title="المخزن">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-ink">المخزن</h1>
        <a href="{{ route('inventory.movements.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">
            سجل المخزن
        </a>
    </div>

    {{--
        One card per material type that actually holds stock, plus the total.
        The loop is the point: a third material type added tomorrow gets its own
        card without anyone editing this file.
    --}}
    <div class="mb-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stockSummary['by_type'] as $card)
            <div class="rounded-xl border border-border bg-surface p-5 shadow-sm">
                <p class="text-sm text-secondary">قيمة {{ $card['label'] }}</p>
                <p class="mt-1 text-xl font-bold text-ink"><x-money :amount="$card['value']" /></p>
            </div>
        @endforeach

        <div class="rounded-xl border border-primary/30 bg-primary/10 p-5 shadow-sm">
            <p class="text-sm text-secondary">إجمالي قيمة المخزن</p>
            <p class="mt-1 text-xl font-bold text-primary"><x-money :amount="$stockSummary['total']" /></p>
        </div>
    </div>

    <p class="mb-6 text-xs text-secondary">
        الكروت دي بتعرض قيمة المخزن كلها دايمًا — البحث والفلتر بيؤثروا على الجدول اللي تحت بس.
    </p>

    <x-quick-add :action="route('inventory.materials.store')" title="إضافة مادة">
        <x-quick-field name="name" label="اسم المادة" width="w-56" required />
        <div>
            <label for="material_type_id" class="mb-1 block text-xs font-medium text-ink-soft">نوع الخامة</label>
            <select id="material_type_id" name="material_type_id" required class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
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
        <x-quick-field name="unit_price" label="سعر الوحدة (ج.م)" inputmode="decimal" width="w-32" placeholder="125.50" required />
        <x-quick-field name="quantity" label="كمية مبدئية" inputmode="decimal" width="w-32" placeholder="اتركها فاضية = صفر" />
        <x-payment-method-select />
        <p class="w-full text-xs text-secondary">
            لو كتبت كمية مبدئية، هتتسجل كشراء: بتخصم من الخزنة بالتاريخ النهارده وبطريقة الدفع المختارة.
        </p>
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
                class="w-full rounded-full border border-transparent bg-bg-subtle py-2 ps-9 pe-3 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
        </div>
        <select
            name="material_type_id"
            class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
        >
            <option value="">كل الأنواع</option>
            @foreach ($materialTypes as $materialType)
                <option value="{{ $materialType->id }}" @selected($selectedTypeId === $materialType->id)>{{ $materialType->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Search') }}
        </button>
        @if ($search !== '' || $selectedTypeId > 0)
            <a href="{{ route('inventory.materials.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">
                إلغاء الفلترة
            </a>
        @endif
    </form>

    @php
        $materialHeadings = ['اسم المادة', 'النوع', 'الكمية الحالية', 'سعر الوحدة', 'القيمة', __('Actions')];
    @endphp

    <x-data-table
        :headings="$materialHeadings"
        :rows="$materials"
        :empty="$search !== '' ? 'لا توجد مادة بهذا الاسم.' : ($selectedTypeId > 0 ? 'لا توجد مادة من النوع ده.' : null)"
    >
        @foreach ($materials as $material)
            <tr>
                <td class="px-4 py-2">{{ $material->name }}</td>
                <td class="px-4 py-2">{{ $material->materialType?->name ?? '—' }}</td>
                <td class="px-4 py-2">
                    <x-quantity :amount="(int) ($stockByMaterial[$material->id] ?? 0)" :unit="$material->unit" />
                </td>
                <td class="px-4 py-2"><x-money :amount="$material->unit_price" /></td>
                {{-- Priced by InventoryService, not by the view — the
                     quantity × price rounding has exactly one implementation. --}}
                <td class="px-4 py-2"><x-money :amount="$valueByMaterial[$material->id] ?? 0" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('inventory.materials.edit', $material) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <a href="{{ route('inventory.movements.index', ['q' => $material->name]) }}" class="ms-3 text-secondary hover:text-primary hover:underline">سجل الحركة</a>
                    <x-delete-button :action="route('inventory.materials.destroy', $material)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $materials->links() }}
    </div>
</x-app-layout>
