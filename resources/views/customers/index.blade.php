<x-app-layout title="العملاء">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">العملاء</h1>
        <a href="{{ route('customers.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

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
