<x-app-layout title="العملاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">العملاء</h1>

    <x-quick-add :action="route('customers.store')" title="إضافة عميل">
        <x-quick-field name="name" label="اسم العميل" width="w-56" required />
        <x-quick-field name="phone" label="رقم الهاتف" width="w-40" />
        <x-quick-field name="address" label="العنوان" width="w-64" />
    </x-quick-add>

    <x-data-table :headings="['الاسم', 'رقم الهاتف', 'عدد الغرف', __('Actions')]" :rows="$customers">
        @foreach ($customers as $customer)
            <tr>
                <td class="px-4 py-2">
                    <a href="{{ route('customers.show', $customer) }}" class="text-primary hover:underline">{{ $customer->name }}</a>
                </td>
                <td class="px-4 py-2">{{ $customer->phone ?? '—' }}</td>
                <td class="px-4 py-2">{{ $customer->rooms_count }}</td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('customers.edit', $customer) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('customers.destroy', $customer)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-app-layout>
