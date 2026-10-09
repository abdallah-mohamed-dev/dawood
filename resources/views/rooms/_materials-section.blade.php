@php
    $locked = $room->status === \App\Enums\RoomStatus::Completed;
    $rows = $room->roomMaterials;
    $shortCount = $rows->filter(fn ($roomMaterial) => ! $roomMaterial->isFullyIssued() && $roomMaterial->isShort((int) ($stockByMaterial[$roomMaterial->material_id] ?? 0)))->count();
@endphp

<x-panel
    id="materials-section"
    x-data="{ formOpen: false, type: 'all', q: '' }"
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
        <input type="search" x-model="q" placeholder="ابحث عن مادة..." class="min-w-0 flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
    </div>

    @unless ($locked)
        <div x-show="formOpen" x-cloak x-data="{ selectedType: '' }" class="mb-4 rounded-lg border border-border-soft bg-bg-subtle p-3">
            <form method="POST" action="{{ route('rooms.materials.store', $room) }}" class="flex flex-wrap items-end gap-3">
                @csrf
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
                    <select name="material_id" required class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="">اختر مادة</option>
                        @foreach ($availableMaterials as $material)
                            <option
                                value="{{ $material->id }}"
                                data-label="{{ $material->name }} ({{ $material->unit }})"
                                x-show="selectedType === '' || selectedType === '{{ $material->material_type_id }}'"
                            >{{ $material->name }} ({{ $material->unit }})</option>
                        @endforeach
                    </select>
                    @error('material_id')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-soft">الكمية المطلوبة</label>
                    <input type="number" step="0.001" min="0" name="required_quantity" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
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
