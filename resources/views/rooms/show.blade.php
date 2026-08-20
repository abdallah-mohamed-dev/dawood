@php
    $hasIssuedMaterials = $room->hasIssuedMaterials();
@endphp

<x-app-layout title="{{ $room->room_type }}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $room->room_type }}</h1>
            <p class="mt-1 text-sm text-secondary">
                العميل:
                <a href="{{ route('customers.show', $room->customer) }}" class="text-primary hover:underline">{{ $room->customer->name }}</a>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('rooms.status.update', $room) }}">
                @csrf
                <select name="status" onchange="this.form.submit()" class="rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($room->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </form>

            @if ($hasIssuedMaterials)
                <button type="button" onclick="document.getElementById('delete-room-dialog').showModal()" class="rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    حذف الغرفة
                </button>
            @else
                <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        حذف الغرفة
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">سعر البيع</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$room->sale_price" /></div>
        </div>
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">تكلفة الخامات</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$room->materialsCost()" /></div>
        </div>
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">المدفوع</div>
            <div class="mt-1 text-xl font-bold text-success"><x-money :amount="$room->paidAmount()" /></div>
        </div>
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">المتبقي</div>
            <div class="mt-1 text-xl font-bold text-danger"><x-money :amount="$room->remainingAmount()" /></div>
        </div>
    </div>

    <div class="mb-6 rounded-lg border border-border bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة احتياج مادة</h2>
        <form method="POST" action="{{ route('rooms.materials.store', $room) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="material_id" class="mb-1 block text-xs font-medium text-gray-700">المادة</label>
                <select id="material_id" name="material_id" required class="w-56 rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    <option value="">اختر مادة</option>
                    @foreach ($availableMaterials as $material)
                        <option value="{{ $material->id }}">{{ $material->category->name }} — {{ $material->name }} ({{ $material->unit }})</option>
                    @endforeach
                </select>
                @error('material_id')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="required_quantity" class="mb-1 block text-xs font-medium text-gray-700">الكمية المطلوبة</label>
                <input id="required_quantity" type="number" step="0.001" min="0" name="required_quantity" class="w-32 rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                @error('required_quantity')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">{{ __('Add') }}</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المادة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المطلوب</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المصروف</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">التكلفة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المخزون الحالي</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($room->roomMaterials as $roomMaterial)
                    <tr>
                        <td class="px-4 py-2">{{ $roomMaterial->material->name }}</td>
                        <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->required_quantity" :unit="$roomMaterial->material->unit" /></td>
                        <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->issued_quantity" :unit="$roomMaterial->material->unit" /></td>
                        <td class="px-4 py-2"><x-money :amount="$roomMaterial->cost" /></td>
                        <td class="px-4 py-2"><x-quantity :amount="(int) ($stockByMaterial[$roomMaterial->material_id] ?? 0)" :unit="$roomMaterial->material->unit" /></td>
                        <td class="px-4 py-2 text-end">
                            @if (! $roomMaterial->isFullyIssued())
                                <form method="POST" action="{{ route('rooms.materials.issue', [$room, $roomMaterial]) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.001" min="0" name="quantity" placeholder="الكمية" class="w-24 rounded-md border border-border px-2 py-1 text-xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                                    <button type="submit" class="text-primary hover:underline">صرف</button>
                                </form>
                            @endif
                            @if (! $roomMaterial->hasBeenIssued())
                                <form method="POST" action="{{ route('rooms.materials.destroy', [$room, $roomMaterial]) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </form>
                            @endif
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

    <div class="mb-6 mt-6 rounded-lg border border-border bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة دفعة</h2>
        <form method="POST" action="{{ route('rooms.payments.store', $room) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
                <input id="amount" type="number" step="0.01" min="0" name="amount" class="w-32 rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                @error('amount')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="paid_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
                <input id="paid_at" type="date" name="paid_at" value="{{ now()->toDateString() }}" class="rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                @error('paid_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="note" class="mb-1 block text-xs font-medium text-gray-700">ملاحظة</label>
                <input id="note" type="text" name="note" class="w-48 rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
            </div>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">{{ __('Add') }}</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">التاريخ</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">ملاحظة</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">المبلغ</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($room->customerPayments as $payment)
                    <tr>
                        <td class="px-4 py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2 text-secondary">{{ $payment->note ?? '—' }}</td>
                        <td class="px-4 py-2"><x-money :amount="$payment->amount" /></td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('payments.edit', $payment) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($hasIssuedMaterials)
        <dialog id="delete-room-dialog" class="w-full max-w-md rounded-lg border border-border p-6 backdrop:bg-black/40">
            <h2 class="mb-3 text-lg font-semibold text-gray-900">تأكيد حذف الغرفة</h2>
            <p class="mb-4 text-sm text-secondary">صُرفت خامات لهذه الغرفة. اختر ماذا يحدث لها:</p>

            <ul class="mb-4 space-y-1 text-sm text-secondary">
                @foreach ($room->roomMaterials as $roomMaterial)
                    @if ($roomMaterial->hasBeenIssued())
                        <li>
                            {{ $roomMaterial->material->name }}:
                            <x-quantity :amount="$roomMaterial->issued_quantity" :unit="$roomMaterial->material->unit" />
                            (<x-money :amount="$roomMaterial->cost" />)
                        </li>
                    @endif
                @endforeach
            </ul>

            <form method="POST" action="{{ route('rooms.destroy', $room) }}">
                @csrf
                @method('DELETE')

                <div class="mb-4 space-y-2">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="return_materials" value="1" required class="mt-1">
                        <span>إرجاع الخامات المصروفة للمخزون</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="return_materials" value="0" required class="mt-1">
                        <span>الخامات استُهلكت فعلًا (بدون إرجاع)</span>
                    </label>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        تأكيد الحذف
                    </button>
                    <button type="button" onclick="document.getElementById('delete-room-dialog').close()" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</x-app-layout>
