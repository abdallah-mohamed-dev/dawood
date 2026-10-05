{{-- One table of a room's material requirements. Used by each type box and by the combined view. --}}
<div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
    <table class="min-w-full divide-y divide-border text-sm">
        <thead class="bg-bg-subtle">
            <tr>
                @if ($showType)
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">النوع</th>
                @endif
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المادة</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المطلوب</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المصروف</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">التكلفة</th>
                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المخزن الحالي</th>
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
                $colspan = $showType ? 7 : 6;
            @endphp

            <tbody x-data="{ editing: false }" class="divide-y divide-border {{ $borderClass }}">
                <tr>
                    @if ($showType)
                        <td class="px-4 py-2">{{ $roomMaterial->material->materialType?->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-2">{{ $roomMaterial->material->name }}</td>
                    <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->required_quantity" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2"><x-quantity :amount="$roomMaterial->issued_quantity" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2"><x-money :amount="$roomMaterial->cost" /></td>
                    <td class="px-4 py-2"><x-quantity :amount="$stock" :unit="$roomMaterial->material->unit" /></td>
                    <td class="px-4 py-2 text-end">
                        <button
                            type="button"
                            @click="editing = ! editing"
                            @disabled($locked)
                            @if ($locked) title="الغرفة مكتملة ومقفولة" @endif
                            class="text-primary hover:underline disabled:text-secondary disabled:no-underline disabled:cursor-not-allowed"
                        >تعديل</button>

                        @if ($fullyIssued || $locked)
                            <button
                                type="button"
                                disabled
                                title="{{ $locked ? 'الغرفة مكتملة ومقفولة' : 'تم صرف الكمية كاملة' }}"
                                class="ms-3 text-secondary cursor-not-allowed"
                            >صرف</button>
                        @else
                            {{-- Errors render once only: the combined table is in the page too (hidden), and would repeat them. --}}
                            @php $issueFailed = ! $showType && $errors->getBag($issueBag)->has('quantity'); @endphp
                            <form method="POST" action="{{ route('rooms.materials.issue', [$room, $roomMaterial]) }}" class="inline-flex flex-col items-start gap-1">
                                @csrf
                                <span class="inline-flex items-center gap-2">
                                    <input type="number" step="0.001" min="0" name="quantity" value="{{ $issueFailed ? old('quantity') : '' }}" placeholder="الكمية" class="w-24 rounded-md border border-border px-2 py-1 text-xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                                    <button type="submit" class="text-primary hover:underline">صرف</button>
                                </span>
                                @unless ($showType)
                                    @error('quantity', $issueBag)
                                        <span class="text-xs text-danger">{{ $message }}</span>
                                    @enderror
                                @endunless
                            </form>
                        @endif

                        @if ($locked)
                            <button type="button" disabled title="الغرفة مكتملة ومقفولة" class="ms-3 text-secondary cursor-not-allowed">حذف</button>
                        @else
                            <form method="POST" action="{{ route('rooms.materials.destroy', [$room, $roomMaterial]) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>

                @unless ($locked)
                    <tr x-show="editing" class="bg-bg-subtle" style="display: none;">
                        <td colspan="{{ $colspan }}" class="px-4 py-3">
                            <form method="POST" action="{{ route('rooms.materials.update', [$room, $roomMaterial]) }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-700">الكمية المطلوبة</label>
                                    <input type="number" step="0.001" min="0" name="required_quantity" value="{{ old('required_quantity', $roomMaterial->required_quantity) }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                                    @unless ($showType)
                                        @error('required_quantity', $editBag)
                                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                        @enderror
                                    @endunless
                                </div>
                                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Save') }}</button>
                                <button type="button" @click="editing = false" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">{{ __('Cancel') }}</button>
                            </form>
                        </td>
                    </tr>
                @endunless

                @if ($short)
                    <tr>
                        <td colspan="{{ $colspan }}" class="px-4 pb-2 text-xs text-danger">
                            هذه الخامة ناقصة ومطلوب شراؤها.
                            <a href="{{ route('inventory.shortages.index', ['room_id' => $room->id]) }}" class="underline">شوف المشتريات المطلوبة</a>
                        </td>
                    </tr>
                @endif
            </tbody>
        @empty
            <tbody>
                <tr>
                    <td colspan="{{ $showType ? 7 : 6 }}" class="px-4 py-6 text-center text-secondary">لا توجد مواد مضافة لهذه الغرفة.</td>
                </tr>
            </tbody>
        @endforelse
    </table>
</div>
