{{--
    One material, in two states in the same row: read mode and edit mode,
    swapped by Alpine without leaving the page.

    The <form> cannot wrap a <tr> (the parser would hoist it out of the table),
    so it is rendered once per row *after* the table and the inputs reach it
    through the HTML `form` attribute. That is also why every input here
    repeats `form="..."`.

    $reopen is true for the one row whose save just failed validation: that row
    comes back already open, showing old() and the error. Every other row keeps
    its stored values, because old() is shared by the whole page.
--}}
@props(['material', 'stock', 'value', 'materialTypes', 'reopen' => false])

@php
    $formId = 'material-edit-'.$material->id;
    $inputClass = 'w-full rounded-lg border border-border bg-surface px-2 py-1 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
@endphp

<tr x-data="{ editing: @js((bool) $reopen) }" :class="editing && 'bg-primary/5'">
    <td class="px-4 py-2 align-top">
        <span x-show="! editing">{{ $material->name }}</span>
        <input
            x-show="editing" x-cloak form="{{ $formId }}" name="name" type="text" required
            value="{{ $reopen ? old('name', $material->name) : $material->name }}"
            class="{{ $inputClass }} min-w-40"
        >
        @if ($reopen)
            @error('name', 'materialRow')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        @endif
    </td>

    <td class="px-4 py-2 align-top">
        <span x-show="! editing">{{ $material->materialType?->name ?? '—' }}</span>
        <select x-show="editing" x-cloak form="{{ $formId }}" name="material_type_id" required class="{{ $inputClass }}">
            @foreach ($materialTypes as $materialType)
                <option value="{{ $materialType->id }}" @selected((int) ($reopen ? old('material_type_id', $material->material_type_id) : $material->material_type_id) === $materialType->id)>{{ $materialType->name }}</option>
            @endforeach
        </select>
        @if ($reopen)
            @error('material_type_id', 'materialRow')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        @endif
    </td>

    <td class="px-4 py-2 align-top">
        <span x-show="! editing"><x-quantity :amount="$stock" :unit="$material->unit" /></span>
        <span x-show="editing" x-cloak class="flex items-center gap-1">
            {{-- inputmode numeric, not decimal: quantities are whole units. --}}
            <input
                form="{{ $formId }}" name="quantity" type="text" inputmode="numeric" required
                value="{{ $reopen ? old('quantity', \App\Casts\QuantityCast::toDecimalString((int) $material->getRawOriginal('quantity'))) : \App\Casts\QuantityCast::toDecimalString((int) $material->getRawOriginal('quantity')) }}"
                class="{{ $inputClass }} w-20"
            >
            <input form="{{ $formId }}" name="unit" type="text" required value="{{ $reopen ? old('unit', $material->unit) : $material->unit }}" class="{{ $inputClass }} w-20">
        </span>
        @if ($reopen)
            @error('quantity', 'materialRow')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            @error('unit', 'materialRow')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        @endif
    </td>

    <td class="px-4 py-2 align-top">
        <span x-show="! editing"><x-money :amount="$material->unit_price" /></span>
        <input
            x-show="editing" x-cloak form="{{ $formId }}" name="unit_price" type="text" inputmode="numeric" required
            value="{{ $reopen ? old('unit_price', \App\Casts\MoneyCast::toDecimalString((int) $material->getRawOriginal('unit_price'))) : \App\Casts\MoneyCast::toDecimalString((int) $material->getRawOriginal('unit_price')) }}"
            class="{{ $inputClass }} w-24"
        >
        @if ($reopen)
            @error('unit_price', 'materialRow')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        @endif
    </td>

    {{-- Priced by InventoryService, not by the view — the quantity × price
         product has exactly one implementation. Read-only either way. --}}
    <td class="px-4 py-2 align-top"><x-money :amount="$value" /></td>

    <td class="px-4 py-2 text-end align-top">
        <span x-show="! editing" class="whitespace-nowrap">
            <button type="button" x-on:click="editing = true" class="text-primary hover:underline">{{ __('Edit') }}</button>
            <a href="{{ route('inventory.movements.index', ['q' => $material->name]) }}" class="ms-3 text-secondary hover:text-primary hover:underline">سجل الحركة</a>
            <x-delete-button :action="route('inventory.materials.destroy', $material)" class="ms-3" />
        </span>
        <span x-show="editing" x-cloak class="whitespace-nowrap">
            <button type="submit" form="{{ $formId }}" class="rounded-md bg-primary px-3 py-1 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark">{{ __('Save') }}</button>
            <button type="button" x-on:click="editing = false" class="ms-2 text-sm text-secondary hover:text-danger hover:underline">إلغاء</button>
        </span>
    </td>
</tr>
