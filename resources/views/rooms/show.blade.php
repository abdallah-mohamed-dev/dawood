@php
    $hasIssuedMaterials = $room->hasIssuedMaterials();
@endphp

<x-app-layout title="{{ $room->room_type }}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $room->room_type }}</h1>
            <p class="mt-1 text-sm text-secondary">
                العميل:
                <a href="{{ route('customers.show', $room->customer) }}" class="text-primary hover:underline">{{ $room->customer->name }}</a>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('rooms.status.update', $room) }}">
                @csrf
                <select name="status" onchange="this.form.submit()" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($room->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </form>

            @if ($hasIssuedMaterials)
                <button type="button" onclick="document.getElementById('delete-room-dialog').showModal()" class="rounded-lg bg-danger px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:opacity-90 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-danger/40 focus:ring-offset-2">
                    حذف الغرفة
                </button>
            @else
                <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-danger px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:opacity-90 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-danger/40 focus:ring-offset-2">
                        حذف الغرفة
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">سعر البيع</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['sale_price']" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكلفة الخامات</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['materials']" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المصنعية</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['labor']" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">مصروفات أخرى</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['other']" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إجمالي التكلفة</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['total_cost']" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">{{ $room->status->countsTowardProfit() ? 'الربح' : 'الربح المتوقع' }}</div>
            <div class="mt-1 text-xl font-bold {{ $profit['profit'] < 0 ? 'text-danger' : 'text-success' }}">
                <x-money :amount="$profit['profit']" />
            </div>
            <p class="mt-1 text-xs text-secondary">
                لا يشمل المصروفات الإدارية.
                @unless ($room->status->countsTowardProfit())
                    الخامات غير المصروفة لم تُحسب بعد.
                @endunless
            </p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المدفوع</div>
            <div class="mt-1 text-xl font-bold text-success"><x-money :amount="$room->paidAmount()" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المتبقي</div>
            <div class="mt-1 text-xl font-bold text-danger"><x-money :amount="$room->remainingAmount()" /></div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة احتياج مادة</h2>
        <form method="POST" action="{{ route('rooms.materials.store', $room) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="material_id" class="mb-1 block text-xs font-medium text-gray-700">المادة</label>
                <select id="material_id" name="material_id" required class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option value="">اختر مادة</option>
                    @foreach ($availableMaterials as $material)
                        <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit }})</option>
                    @endforeach
                </select>
                @error('material_id')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="required_quantity" class="mb-1 block text-xs font-medium text-gray-700">الكمية المطلوبة</label>
                <input id="required_quantity" type="number" step="0.001" min="0" name="required_quantity" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('required_quantity')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
        </form>
    </div>

    <x-data-table :headings="['المادة', 'المطلوب', 'المصروف', 'التكلفة', 'المخزون الحالي', __('Actions')]" :rows="$room->roomMaterials">
        @foreach ($room->roomMaterials as $roomMaterial)
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
        @endforeach
    </x-data-table>

    @include('rooms._costs-section', [
        'room' => $room,
        'type' => \App\Enums\RoomCostType::Labor,
        'title' => 'المصنعية',
        'descriptionLabel' => 'الوصف',
        'emptyMessage' => 'لا توجد دفعات مصنعية لهذه الغرفة.',
    ])

    @include('rooms._costs-section', [
        'room' => $room,
        'type' => \App\Enums\RoomCostType::Other,
        'title' => 'مصروفات إضافية',
        'descriptionLabel' => 'السبب',
        'emptyMessage' => 'لا توجد مصروفات إضافية لهذه الغرفة.',
    ])

    <div class="mb-6 mt-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة دفعة</h2>
        <form method="POST" action="{{ route('rooms.payments.store', $room) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
                <input id="amount" type="number" step="0.01" min="0" name="amount" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('amount')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="paid_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
                <input id="paid_at" type="date" name="paid_at" value="{{ now()->toDateString() }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('paid_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="note" class="mb-1 block text-xs font-medium text-gray-700">ملاحظة</label>
                <input id="note" type="text" name="note" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
        </form>
    </div>

    <x-data-table :headings="['التاريخ', 'ملاحظة', 'المبلغ', __('Actions')]" :rows="$room->customerPayments">
        @foreach ($room->customerPayments as $payment)
            <tr>
                <td class="px-4 py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2 text-secondary">{{ $payment->note ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$payment->amount" /></td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('payments.edit', $payment) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                    <x-delete-button :action="route('payments.destroy', $payment)" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

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
                    <button type="submit" class="rounded-lg bg-danger px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:opacity-90 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-danger/40 focus:ring-offset-2">
                        تأكيد الحذف
                    </button>
                    <button type="button" onclick="document.getElementById('delete-room-dialog').close()" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                        إلغاء
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</x-app-layout>
