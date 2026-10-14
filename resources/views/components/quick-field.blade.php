@props(['name', 'label', 'type' => 'text', 'value' => null, 'width' => 'w-40', 'id' => null, 'bag' => 'default'])

{{-- A compact field for the horizontal quick-add row, and for the inline
     edit rows under each table (specs/024).

     old() is shared by the whole page. When the request that just failed was
     a row edit (it carries editing_id), its values belong to that row — so an
     add form must not pick them up as its own. --}}
@php
    $inputId = $id ?? $name;
    $current = $value ?? (old('editing_id') === null ? old($name) : null);
@endphp

<div>
    <label for="{{ $inputId }}" class="mb-1 block text-xs font-medium text-ink-soft">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $current }}"
        {{ $attributes->merge(['class' => $width.' rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30']) }}
    >
    @error($name, $bag)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
