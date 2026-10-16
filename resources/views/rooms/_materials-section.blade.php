@php
    $locked = $room->status === \App\Enums\RoomStatus::Completed;
    $rows = $room->roomMaterials;
    $shortCount = $rows->filter(fn ($roomMaterial) => ! $roomMaterial->isFullyIssued() && $roomMaterial->isShort((int) ($stockByMaterial[$roomMaterial->material_id] ?? 0)))->count();
@endphp

<x-panel
    id="materials-section"
    x-data="{ formOpen: false, type: 'all' }"
    x-on:open-materials-form.window="formOpen = true"
>
    <x-slot:title>
        مواد الغرفة
        <span class="text-xs font-normal text-secondary">({{ $rows->count() }} {{ $rows->count() === 1 ? 'مادة' : 'مواد' }}@if ($shortCount > 0) · {{ $shortCount }} ناقصة @endif)</span>
    </x-slot:title>

    <x-slot:actions>
        @unless ($locked)
            <button type="button" @click="formOpen = ! formOpen" class="rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink-soft transition-colors hover:bg-bg-subtle hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/40">+ إضافة مادة</button>
        @endunless
    </x-slot:actions>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-lg border border-border bg-bg-subtle p-0.5 text-xs font-medium" role="group" aria-label="فلتر نوع المادة">
            <button type="button" @click="type = 'all'" :class="type === 'all' ? 'bg-surface text-ink shadow-sm' : 'text-secondary'" class="rounded-md px-3 py-1.5 transition-colors">الكل</button>
            @foreach ($materialTypes as $materialType)
                <button
                    type="button"
                    @click="type = '{{ $materialType->id }}'"
                    :class="type === '{{ $materialType->id }}' ? 'bg-surface text-ink shadow-sm' : 'text-secondary'"
                    class="rounded-md px-3 py-1.5 transition-colors"
                >{{ $materialType->name === 'خامة' ? 'خامات' : ($materialType->name === 'اكسسوار' ? 'اكسسوارات' : $materialType->name) }}</button>
            @endforeach
        </div>
    </div>

    @unless ($locked)
        {{--
            The search lives here, on the list you pick from — the warehouse
            can hold hundreds of materials, while a room's own table is short.
            It ignores أ/إ/آ vs ا, ة vs ه and ى vs ي (normalizeArabic in
            app.js), and when exactly one material matches it is picked.
        --}}
        <div
            x-show="formOpen"
            x-cloak
            x-data="{
                selectedType: '',
                search: '',
                visible(option) {
                    return (this.selectedType === '' || this.selectedType === option.dataset.type)
                        && (this.search === '' || normalizeArabic(option.dataset.label).includes(normalizeArabic(this.search)));
                },
                matches() {
                    return [...this.$refs.material.options].filter((option) => option.value !== '' && this.visible(option));
                },
                pickSingle() {
                    const hits = this.matches();
                    const current = this.$refs.material.selectedOptions[0];

                    if (hits.length === 1) {
                        this.$refs.material.value = hits[0].value;
                    } else if (current && current.value !== '' && ! this.visible(current)) {
                        this.$refs.material.value = '';
                    }
                },
            }"
            x-effect="search; selectedType; $nextTick(() => pickSingle())"
            class="mb-4 rounded-lg border border-border-soft bg-bg-subtle p-3"
        >
            <form method="POST" action="{{ route('rooms.materials.store', $room) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="material_search" class="mb-1 block text-xs font-medium text-ink-soft">ابحث عن مادة</label>
                    <input id="material_search" type="search" x-model="search" placeholder="اكتب جزء من الاسم" autocomplete="off" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <p class="mt-1 text-xs text-secondary" x-show="search !== '' || selectedType !== ''" x-text="matches().length + ' نتيجة'"></p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-soft">النوع</label>
                    <select x-model="selectedType" class="w-36 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="">كل الأنواع</option>
                        @foreach ($materialTypes as $materialType)
                            <option value="{{ $materialType->id }}">{{ $materialType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-soft">المادة</label>
                    <select name="material_id" x-ref="material" required class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="">اختر مادة</option>
                        @foreach ($availableMaterials as $material)
                            <option
                                value="{{ $material->id }}"
                                data-label="{{ $material->name }} ({{ $material->unit }})"
                                data-type="{{ $material->material_type_id }}"
                                x-show="visible($el)"
                            >{{ $material->name }} ({{ $material->unit }})</option>
                        @endforeach
                    </select>
                    @error('material_id')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-soft">الكمية المطلوبة</label>
                    <input type="number" step="1" min="0" name="required_quantity" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                    @error('required_quantity')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Add') }}</button>
                <button type="button" @click="formOpen = false" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">{{ __('Cancel') }}</button>
            </form>
        </div>
    @endunless

    @include('rooms._material-table', ['rows' => $rows])
</x-panel>
