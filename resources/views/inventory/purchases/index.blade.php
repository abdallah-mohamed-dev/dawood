<x-app-layout title="مشتريات المخزون">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">مشتريات المخزون</h1>
        <a href="{{ route('inventory.purchases.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

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
