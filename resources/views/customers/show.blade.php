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
        </div>
    </div>

    <x-quick-add :action="route('customers.rooms.store', $customer)" title="إضافة غرفة">
        <x-quick-field name="room_type" label="نوع الغرفة" width="w-56" placeholder="مثال: غرفة نوم" required />
        <x-quick-field name="sale_price" label="سعر البيع (ج.م)" type="number" step="0.01" min="0" width="w-40" required />
    </x-quick-add>

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
