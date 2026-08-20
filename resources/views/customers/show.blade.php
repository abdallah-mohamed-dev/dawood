<x-app-layout title="{{ $customer->name }}">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ $customer->phone ?? 'بدون رقم هاتف' }}
                @if ($customer->address)
                    — {{ $customer->address }}
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('customers.edit', $customer) }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Edit') }}
            </a>
            <a href="{{ route('rooms.create', ['customer_id' => $customer->id]) }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                إضافة غرفة
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">نوع الغرفة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">الحالة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">سعر البيع</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المتبقي</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($rooms as $room)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">{{ $room->room_type }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $room->status->label() }}</td>
                        <td class="px-4 py-2"><x-money :amount="$room->sale_price" /></td>
                        <td class="px-4 py-2"><x-money :amount="$room->remainingAmount()" /></td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('rooms.show', $room) }}" class="text-primary hover:underline">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
