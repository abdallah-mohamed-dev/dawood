<x-app-layout title="المشتريات المطلوبة">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">المشتريات المطلوبة</h1>

    <form method="GET" action="{{ route('inventory.shortages.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم الخامة" class="w-full rounded-full border border-transparent bg-bg-subtle py-2 px-4 text-sm text-gray-900 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <select name="room_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-gray-900 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل الغرف</option>
            @foreach ($activeRooms as $room)
                <option value="{{ $room->id }}" @selected($filters['room_id'] === $room->id)>{{ $room->room_type }}</option>
            @endforeach
        </select>

        <select name="customer_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-gray-900 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل العملاء</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected($filters['customer_id'] === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>

        <select name="material_type_id" class="rounded-full border border-transparent bg-bg-subtle px-4 py-2 text-sm text-gray-900 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">كل الأنواع</option>
            @foreach ($materialTypes as $type)
                <option value="{{ $type->id }}" @selected($filters['material_type_id'] === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">بحث</button>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إجمالي التكلفة التقديرية</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$totalCost" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">عدد الغرف</div>
            <div class="mt-1 text-xl font-bold text-gray-900">{{ $rooms->count() }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">عدد الخامات الناقصة</div>
            <div class="mt-1 text-xl font-bold text-gray-900">{{ $lineCount }}</div>
        </div>
    </div>

    @forelse ($rooms as $group)
        @php $room = $group['room']; @endphp
        <div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm" id="room-{{ $room->id }}">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a href="{{ route('rooms.show', $room) }}" class="text-base font-semibold text-primary hover:underline">{{ $room->room_type }}</a>
                    <span class="text-sm text-secondary">— العميل: <a href="{{ route('customers.show', $room->customer) }}" class="hover:underline">{{ $room->customer->name }}</a></span>
                    <span class="ms-2"><x-status-badge :status="$room->status" /></span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-secondary">الإجمالي: <x-money :amount="$group['total']" /></span>
                    <a href="{{ route('inventory.shortages.print', $room) }}" target="_blank" class="rounded-lg border border-border px-3 py-1.5 text-sm text-gray-700 hover:bg-bg">طباعة أمر الشراء</a>
                </div>
            </div>

            <x-data-table :headings="['الخامة', 'النوع', 'المطلوب', 'المصروف', 'المتاح بالمخزن', 'الناقص', 'سعر الوحدة', 'التكلفة التقديرية']" :rows="$group['lines']">
                @foreach ($group['lines'] as $line)
                    @php $roomMaterial = $line['roomMaterial']; $material = $roomMaterial->material; @endphp
                    <tr>
                        <td class="px-4 py-2">{{ $material->name }}</td>
                        <td class="px-4 py-2">{{ $material->materialType?->name ?? '—' }}</td>
                        <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->required_quantity" :unit="$material->unit" /></td>
                        <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->issued_quantity" :unit="$material->unit" /></td>
                        <td class="px-4 py-2"><x-quantity :amount="$line['stock']" :unit="$material->unit" /></td>
                        <td class="px-4 py-2 font-bold text-danger"><x-quantity :amount="$line['shortage']" :unit="$material->unit" /></td>
                        <td class="px-4 py-2"><x-money :amount="$material->getRawOriginal('unit_price')" /></td>
                        <td class="px-4 py-2"><x-money :amount="$line['cost']" /></td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    @empty
        <div class="rounded-xl border border-border bg-surface p-6 text-center text-secondary shadow-sm">
            لا توجد خامات ناقصة — كل احتياجات الغرف متوفرة في المخزن.
        </div>
    @endforelse

    <p class="mt-2 text-xs text-secondary">المخزن بيتوزع على الغرف بالترتيب (الأقدم الأول). الغرفة بتظهر بالناقص اللي بعد ما الغرف اللي قبلها تاخد نصيبها.</p>
</x-app-layout>
