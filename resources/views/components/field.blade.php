@props(['name', 'label', 'type' => 'text', 'value' => null])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input
        id="{{ $name }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $value }}"
        {{ $attributes->merge(['class' => 'w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30']) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>