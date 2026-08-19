<x-app-layout title="مشتريات المخزون">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">مشتريات المخزون</h1>
        <a href="{{ route('inventory.purchases.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('Add') }}
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">تاريخ الشراء</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المادة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">الكمية الأصلية</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المتبقي</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">سعر الوحدة</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="px-4 py-2">{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">{{ $purchase->material->name }}</td>
                        <td class="px-4 py-2"><x-quantity :amount="$purchase->quantity" :unit="$purchase->material->unit" /></td>
                        <td class="px-4 py-2"><x-quantity :amount="$purchase->remaining_quantity" :unit="$purchase->material->unit" /></td>
                        <td class="px-4 py-2"><x-money :amount="$purchase->unit_cost" /></td>
                        <td class="px-4 py-2 text-end">
                            <form method="POST" action="{{ route('inventory.purchases.destroy', $purchase) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $purchases->links() }}
    </div>
</x-app-layout>
