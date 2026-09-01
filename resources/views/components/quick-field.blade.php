@props(['name', 'label', 'type' => 'text', 'value' => null, 'width' => 'w-40'])

{{-- A compact field for the horizontal quick-add row (x-field is the tall,
     full-width variant used on the edit pages). --}}
<div>
    <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-gray-700">{{ $label }}</label>
    <input
        id="{{ $name }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $value ?? old($name) }}"
        {{ $attributes->merge(['class' => $width.' rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30']) }}
    >
    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
