@php
    $locked = $room->status === \App\Enums\RoomStatus::Completed;
    $typeTitles = ['خامة' => 'الخامات', 'اكسسوار' => 'الاكسسوارات'];
@endphp

<div class="mb-6" x-data="persistedToggle('room.materials.combined', false)">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink">المواد</h2>
        <button type="button" @click="toggle()" class="text-sm text-primary hover:underline" x-text="open ? 'عرض كل نوع في بوكس' : 'عرض الكل مع بعض'"></button>
    </div>

    {{-- Combined: one table, type as a column. --}}
    <div x-show="open" style="display: none;">
        @include('rooms._material-table', [
            'rows' => $room->roomMaterials,
            'showType' => true,
        ])
    </div>

    {{-- Boxes: one per material type, each with its own add form. --}}
    <div x-show="! open" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        @foreach ($materialTypes as $type)
            @php
                $rows = $room->roomMaterials->filter(fn ($roomMaterial) => $roomMaterial->material->material_type_id === $type->id);
                $options = $availableMaterials
                    ->where('material_type_id', $type->id)
                    ->mapWithKeys(fn ($material) => [$material->id => $material->name.' ('.$material->unit.')']);
            @endphp

            <x-panel :title="$typeTitles[$type->name] ?? $type->name">

                @unless ($locked)
                    <form method="POST" action="{{ route('rooms.materials.store', $room) }}" class="mb-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-soft">{{ $type->name }}</label>
                            <x-searchable-select name="material_id" :options="$options" :required="true" :placeholder="'اختر '.$type->name" />
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
                    </form>
                @endunless

                @include('rooms._material-table', [
                    'rows' => $rows,
                    'showType' => false,
                ])
            </x-panel>
        @endforeach
    </div>
</div>
