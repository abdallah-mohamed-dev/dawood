<x-app-layout title="العملاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">العملاء</h1>

    <form method="GET" action="{{ route('customers.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <input type="search" name="q" value="{{ $search }}" placeholder="ابحث بالاسم أو رقم التليفون" class="w-full rounded-full border border-transparent bg-bg-subtle py-2 px-4 text-sm text-ink focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">بحث</button>
        <a href="{{ route('customers.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">إلغاء البحث</a>
    </form>


    <x-quick-add :action="route('customers.store')" title="إضافة عميل">
        <x-quick-field name="name" label="اسم العميل" width="w-56" required />
        <x-quick-field name="phone" label="رقم الهاتف" width="w-40" />
        <x-quick-field name="address" label="العنوان" width="w-64" />
    </x-quick-add>

    {{-- The row whose save just failed comes back open (see x-edit-row). --}}
    @php $reopenId = (int) old('editing_id', 0); @endphp

    <div x-data="{ editing: {{ $reopenId }} }">
        <x-data-table :headings="['الاسم', 'رقم الهاتف', 'العنوان', 'عدد الغرف', __('Actions')]" :rows="$customers">
            @foreach ($customers as $customer)
                @php
                    $reopen = $reopenId === $customer->id;
                    $bag = $reopen ? 'row' : 'none';
                @endphp
                <tr>
                    <td class="px-4 py-2">
                        <a href="{{ route('customers.show', $customer) }}" class="text-primary hover:underline">{{ $customer->name }}</a>
                    </td>
                    <td class="px-4 py-2">{{ $customer->phone ?? '—' }}</td>
                    <td class="px-4 py-2 text-secondary">{{ $customer->address ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $customer->rooms_count }}</td>
                    <td class="px-4 py-2 text-end whitespace-nowrap">
                        <button type="button" x-on:click="editing = {{ $customer->id }}" class="text-primary hover:underline">{{ __('Edit') }}</button>
                        <x-delete-button :action="route('customers.destroy', $customer)" class="ms-3" />
                    </td>
                </tr>
                <x-edit-row :action="route('customers.update', $customer)" :key="$customer->id" colspan="5">
                    <x-quick-field name="name" :id="'name_'.$customer->id" label="اسم العميل" width="w-56" :bag="$bag" :value="$reopen ? old('name', '') : $customer->name" required />
                    <x-quick-field name="phone" :id="'phone_'.$customer->id" label="رقم الهاتف" width="w-40" :bag="$bag" :value="$reopen ? old('phone', '') : ($customer->phone ?? '')" />
                    <x-quick-field name="address" :id="'address_'.$customer->id" label="العنوان" width="w-64" :bag="$bag" :value="$reopen ? old('address', '') : ($customer->address ?? '')" />
                </x-edit-row>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-app-layout>
