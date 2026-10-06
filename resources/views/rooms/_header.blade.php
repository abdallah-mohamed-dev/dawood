<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $room->room_type }}</h1>
        <p class="mt-1 text-sm text-secondary">
            العميل:
            <a href="{{ route('customers.show', $room->customer) }}" class="text-primary hover:underline">{{ $room->customer->name }}</a>
        </p>
        <p class="mt-1 text-sm text-secondary">تاريخ الإنشاء: {{ $room->created_at->format('Y-m-d') }}</p>
        @if ($room->started_at)
            <p class="mt-1 text-sm text-secondary">بدأ التنفيذ: {{ $room->started_at->format('Y-m-d') }}</p>
        @endif
        @if ($room->priced_at)
            <p class="mt-1 text-sm text-secondary">تاريخ التسعير: {{ $room->priced_at->format('Y-m-d') }}</p>
        @endif
        @if ($room->completed_at)
            <p class="mt-1 text-sm text-secondary">تاريخ الاكتمال: {{ $room->completed_at->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('rooms.status.update', $room) }}">
            @csrf
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
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

@if ($hasIssuedMaterials)
    <dialog id="delete-room-dialog" class="w-full max-w-md rounded-lg border border-border p-6 backdrop:bg-black/40">
        <h2 class="mb-3 text-lg font-semibold text-ink">تأكيد حذف الغرفة</h2>
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
                    <span>إرجاع الخامات المصروفة للمخزن</span>
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
                <button type="button" onclick="document.getElementById('delete-room-dialog').close()" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">
                    إلغاء
                </button>
            </div>
        </form>
    </dialog>
@endif
