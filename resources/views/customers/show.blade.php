<x-app-layout title="{{ $customer->name }}">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ $customer->phone ?? 'بدون رقم هاتف' }}
                @if ($customer->address)
                    — {{ $customer->address }}
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-customer-dialog').showModal()" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">
                {{ __('Edit') }}
            </button>
        </div>
    </div>

    {{-- Edited in a dialog on this page — there is no separate edit page
         (specs/024). Its errors live in the `row` bag, apart from the room
         form below, which posts different fields to the default bag. --}}
    @php $failed = $errors->getBag('row')->isNotEmpty(); @endphp

    <dialog id="edit-customer-dialog" class="w-full max-w-md rounded-lg border border-border p-6 backdrop:bg-black/40">
        <h2 class="mb-4 text-lg font-semibold text-ink">تعديل بيانات العميل</h2>

        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-quick-field name="name" id="edit_customer_name" label="اسم العميل" width="w-full" bag="row" :value="$failed ? old('name', '') : $customer->name" required />
            <x-quick-field name="phone" id="edit_customer_phone" label="رقم الهاتف" width="w-full" bag="row" :value="$failed ? old('phone', '') : ($customer->phone ?? '')" />
            <x-quick-field name="address" id="edit_customer_address" label="العنوان" width="w-full" bag="row" :value="$failed ? old('address', '') : ($customer->address ?? '')" />

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Save') }}</button>
                <button type="button" onclick="document.getElementById('edit-customer-dialog').close()" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">إلغاء</button>
            </div>
        </form>
    </dialog>

    @if ($failed)
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('edit-customer-dialog')?.showModal());</script>
    @endif

    <x-quick-add :action="route('customers.rooms.store', $customer)" title="إضافة غرفة">
        <x-quick-field name="room_type" label="نوع الغرفة" width="w-56" placeholder="مثال: غرفة نوم" required />
        <x-quick-field name="sale_price" label="سعر البيع (ج.م)" type="number" step="1" min="0" width="w-40" required />
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
