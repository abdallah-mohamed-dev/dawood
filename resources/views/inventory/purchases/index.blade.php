<x-app-layout title="مشتريات المخزون">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">مشتريات المخزون</h1>

    <x-quick-add :action="route('inventory.purchases.store')" title="تسجيل عملية شراء">
        <div>
            <label for="material_id" class="mb-1 block text-xs font-medium text-gray-700">المادة</label>
            <select id="material_id" name="material_id" required class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">اختر مادة</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}" @selected((int) old('material_id') === $material->id)>{{ $material->name }} ({{ $material->unit }})</option>
                @endforeach
            </select>
            @error('material_id')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-quick-field name="quantity" label="الكمية" type="number" step="0.001" min="0" width="w-28" required />
        <x-quick-field name="unit_cost" label="سعر الوحدة (ج.م)" type="number" step="0.01" min="0" width="w-32" required />
        <x-quick-field name="purchase_date" label="تاريخ الشراء" type="date" width="w-40" :value="old('purchase_date', now()->toDateString())" required />

        <x-payment-method-select />
    </x-quick-add>

    @php
        $hasFilters = collect($filters)->contains(fn ($value) => $value !== '');
        $thisMonth = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];
        $lastMonth = ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()];
    @endphp

    <form method="GET" action="{{ route('inventory.purchases.index') }}" class="mb-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="mb-1 block text-xs font-medium text-gray-700">اسم المادة</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم المادة" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label for="from" class="mb-1 block text-xs font-medium text-gray-700">من تاريخ</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label for="to" class="mb-1 block text-xs font-medium text-gray-700">إلى تاريخ</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs font-medium text-gray-700">حالة الدفعة</label>
                <select id="status" name="status" class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option value="">الكل</option>
                    <option value="available" @selected($filters['status'] === 'available')>لم يُصرف منها</option>
                    <option value="partial" @selected($filters['status'] === 'partial')>مصروفة جزئيًا</option>
                    <option value="depleted" @selected($filters['status'] === 'depleted')>خلصت بالكامل</option>
                </select>
            </div>

            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Search') }}
            </button>

            @if ($hasFilters)
                <a href="{{ route('inventory.purchases.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                    إلغاء الفلاتر
                </a>
            @endif
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            <a href="{{ route('inventory.purchases.index', $thisMonth) }}" class="rounded-md border border-border px-3 py-1 text-gray-700 hover:bg-bg">الشهر ده</a>
            <a href="{{ route('inventory.purchases.index', $lastMonth) }}" class="rounded-md border border-border px-3 py-1 text-gray-700 hover:bg-bg">الشهر اللي فات</a>
        </div>
    </form>

    <div class="mb-4 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-gray-900">
        <strong>{{ $summary['count'] }}</strong> عملية شراء —
        إجمالي القيمة <strong><x-money :amount="$summary['total']" /></strong>
        @if ($hasFilters)
            <span class="text-secondary">(حسب الفلاتر المحددة)</span>
        @endif
    </div>

    <x-data-table :headings="['تاريخ الشراء', 'المادة', 'الكمية الأصلية', 'المتبقي', 'سعر الوحدة', __('Actions')]" :rows="$purchases" :empty="$hasFilters ? 'لا توجد عمليات شراء مطابقة للفلاتر.' : null">
        @foreach ($purchases as $purchase)
            <tr>
                <td class="px-4 py-2">{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                <td class="px-4 py-2">{{ $purchase->material->name }}</td>
                <td class="px-4 py-2"><x-quantity :amount="$purchase->quantity" :unit="$purchase->material->unit" /></td>
                <td class="px-4 py-2"><x-quantity :amount="$purchase->remaining_quantity" :unit="$purchase->material->unit" /></td>
                <td class="px-4 py-2"><x-money :amount="$purchase->unit_cost" /></td>
                <td class="px-4 py-2 text-end">
                    <x-delete-button :action="route('inventory.purchases.destroy', $purchase)" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $purchases->links() }}
    </div>
</x-app-layout>
