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

    <x-data-table :headings="['تاريخ الشراء', 'المادة', 'الكمية الأصلية', 'المتبقي', 'سعر الوحدة', __('Actions')]" :rows="$purchases">
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
