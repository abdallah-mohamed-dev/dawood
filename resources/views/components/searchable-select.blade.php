{{--
    A <select> with a search box above it. Filtering happens in the browser
    over options already on the page — no request to the server. Reused
    anywhere a list is short enough to ship whole.
--}}
@props(['name', 'options', 'required' => false, 'placeholder' => 'اختر'])

<div x-data="{ q: '' }" class="flex flex-col gap-1">
    <input
        type="search"
        x-model="q"
        placeholder="ابحث..."
        class="w-56 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
    <select
        name="{{ $name }}"
        @if ($required) required @endif
        class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" data-label="{{ $label }}" x-show="q === '' || $el.dataset.label.includes(q)">{{ $label }}</option>
        @endforeach
    </select>
</div>
