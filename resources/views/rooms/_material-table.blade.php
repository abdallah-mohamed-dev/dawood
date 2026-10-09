{{-- The one table of a room's material requirements, filtered client-side by type and search. --}}
<div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
    <table class="min-w-full divide-y divide-border text-sm">
        <thead class="bg-bg-subtle">
            <tr>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المادة</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المطلوب</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المصروف</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المخزن الحالي</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">التكلفة</th>
                <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">إجراءات</th>
            </tr>
        </thead>

        @forelse ($rows as $roomMaterial)
            @php
                $stock = (int) ($stockByMaterial[$roomMaterial->material_id] ?? 0);
                $fullyIssued = $roomMaterial->isFullyIssued();
                // Fully issued wins over short: a material that is used up is not missing.
                $short = ! $fullyIssued && $roomMaterial->isShort($stock);
                $borderClass = $fullyIssued ? 'border-s-4 border-success' : ($short ? 'border-s-4 border-danger' : 'border-s-4 border-transparent');
                $issueBag = 'issue_'.$roomMaterial->id;
                $editBag = 'edit_'.$roomMaterial->id;
                $typeId = (string) $roomMaterial->material->material_type_id;
                $nameKey = \Illuminate\Support\Str::lower($roomMaterial->material->name);
            @endphp

            <tbody
                x-data="{ editing: false }"
                x-show="(type === 'all' || type === '{{ $typeId }}') && (q === '' || $el.dataset.name.includes(q.toLowerCase()))"
                data-name="{{ $nameKey }}"
                class="divide-y divide-border {{ $borderClass }}"
            >
                <tr>
                    <td class="px-4 py-2" data-label="المادة">
                        {{ $roomMaterial->material->name }}
                        <span class="chip ms-1 inline-flex items-center gap-1 rounded-md bg-bg-subtle px-2 py-0.5 text-[11px] font-semibold text-secondary">{{ $roomMaterial->material->materialType?->name ?? '—' }}</span>
                        @if ($fullyIssued)
                            <span class="ms-1 inline-flex items-center gap-1 rounded-md bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">اتصرفت كاملة</span>
                        @elseif ($short)
                            <span class="ms-1 inline-flex items-center gap-1 rounded-md bg-danger/10 px-2 py-0.5 text-[11px] font-semibold text-danger">ناقص <x-quantity :amount="$roomMaterial->shortageQuantity($stock)" :unit="$roomMaterial->material->unit" /></span>
                        @endif
                    </td>
                    <td class="px-4 py-2" data-label="المطلوب"><x-quantity :amount="$roomMaterial->required_quantity" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2" data-label="المصروف"><x-quantity :amount="$roomMaterial->issued_quantity" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2" data-label="المخزن الحالي"><x-quantity :amount="$stock" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2" data-label="التكلفة"><x-money :amount="$roomMaterial->cost" /></td>
                    <td class="px-4 py-2" data-label="إجراءات">
                        <div class="flex justify-end gap-1">
                            <button
                                type="button"
                                @click="editing = ! editing"
                                @disabled($locked)
                                @if ($locked) title="الغرفة مكتملة ومقفولة" @endif
                                class="inline-flex items-center rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink-soft transition-colors hover:bg-bg-subtle hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-40"
                            >تعديل</button>

                            @if ($fullyIssued || $locked)
                                <button
                                    type="button"
                                    disabled
                                    title="{{ $locked ? 'الغرفة مكتملة ومقفولة' : 'تم صرف الكمية كاملة' }}"
                                    class="inline-flex items-center rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink-soft disabled:cursor-not-allowed disabled:opacity-40"
                                >صرف</button>
                            @else
                                <form method="POST" action="{{ route('rooms.materials.issue', [$room, $roomMaterial]) }}" class="inline-flex flex-col items-end gap-1">
                                    @csrf
                                    <span class="inline-flex items-center gap-1">
                                        <input type="number" step="0.001" min="0" name="quantity" value="{{ $errors->getBag($issueBag)->has('quantity') ? old('quantity') : '' }}" placeholder="الكمية" class="w-20 rounded-md border border-border px-2 py-1 text-xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                        <button type="submit" class="inline-flex items-center rounded-md border border-primary bg-primary px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40">صرف</button>
                                    </span>
                                    @error('quantity', $issueBag)
                                        <span class="text-xs text-danger">{{ $message }}</span>
                                    @enderror
                                </form>
                            @endif

                            @if ($locked)
                                <button type="button" disabled title="الغرفة مكتملة ومقفولة" class="inline-flex items-center rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-secondary disabled:cursor-not-allowed disabled:opacity-40">حذف</button>
                            @else
                                <form method="POST" action="{{ route('rooms.materials.destroy', [$room, $roomMaterial]) }}" onsubmit="return confirm('هل أنت متأكد؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-danger transition-colors hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger/40">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>

                @unless ($locked)
                    <tr x-show="editing" class="bg-bg-subtle" style="display: none;">
                        <td colspan="6" class="px-4 py-3">
                            <form method="POST" action="{{ route('rooms.materials.update', [$room, $roomMaterial]) }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-soft">الكمية المطلوبة</label>
                                    <input type="number" step="0.001" min="0" name="required_quantity" value="{{ old('required_quantity', $roomMaterial->required_quantity) }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                                    @error('required_quantity', $editBag)
                                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Save') }}</button>
                                <button type="button" @click="editing = false" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">{{ __('Cancel') }}</button>
                            </form>
                        </td>
                    </tr>
                @endunless

                @if ($short)
                    <tr>
                        <td colspan="6" class="px-4 pb-2 text-xs text-danger">
                            هذه الخامة ناقصة ومطلوب شراؤها.
                            <a href="{{ route('inventory.shortages.index', ['room_id' => $room->id]) }}" class="underline">شوف المشتريات المطلوبة</a>
                        </td>
                    </tr>
                @endif
            </tbody>
        @empty
            <tbody>
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-secondary">لا توجد مواد مضافة لهذه الغرفة.</td>
                </tr>
            </tbody>
        @endforelse
    </table>
</div>
