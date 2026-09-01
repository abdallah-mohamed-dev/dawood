@props(['action', 'title', 'label' => null])

{{--
    The one add form for an index page: always visible, sitting directly
    above the table it feeds. Replaces the separate create pages entirely —
    two routes to the same insert is two places for a new field to be added
    to only one of them.

    Each index page carries exactly one of these, so the default error bag
    is safe here and old() needs no scoping. A page that ever grows a second
    form must give each one a named bag AND scope old() to the form that
    submitted — the bag protects the messages, not the values.
--}}
<div {{ $attributes->merge(['class' => 'mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm']) }}>
    <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ $title }}</h2>

    <form method="POST" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
        @csrf

        {{ $slot }}

        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ $label ?? __('Add') }}
        </button>
    </form>
</div>
