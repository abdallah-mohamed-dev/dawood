<x-app-layout title="{{ $customer->name }}">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ $customer->phone ?? 'بدون رقم هاتف' }}
                @if ($customer->address)
                    — {{ $customer->address }}
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('customers.edit', $customer) }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Edit') }}
            </a>
            <a href="{{ route('rooms.create', ['customer_id' => $customer->id]) }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                إضافة غرفة
            </a>
        </div>
    </div>

    <x-data-table :headings="['نوع الغرفة', 'الحالة', 'سعر البيع', 'المتبقي', __('Actions')]" :rows="$rooms">
        @foreach ($rooms as $room)
            <tr>
                <td class="px-4 py-2">
                    <a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">{{ $room->room_type }}</a>
                </td>
                <td class="px-4 py-2"><x-status-badge :status="$room->status" /></td>
                <td class="px-4 py-2"><x-money :amount="$room->sale_price" /></td>
                <td class="px-4 py-2"><x-money :amount="$room->remainingAmount()" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">عرض</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
