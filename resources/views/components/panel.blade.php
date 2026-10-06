{{--
    The shared card of the ledger direction: a title, an optional sub-line and
    link on the header row, then the body. Footer is optional.
    <x-panel title="..." sub="..." link="route-url" link-label="...">
--}}
@props(['title' => null, 'sub' => null, 'link' => null, 'linkLabel' => 'عرض الكل', 'footer' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface shadow-sm']) }}>
    @if ($title || $link)
        <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border-soft px-4 py-3">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($sub)
                    <p class="mt-0.5 text-xs text-secondary">{{ $sub }}</p>
                @endif
            </div>
            @if ($link)
                <a href="{{ $link }}" class="text-xs font-medium text-primary hover:underline">{{ $linkLabel }}</a>
            @endif
        </header>
    @endif

    <div class="p-4">
        {{ $slot }}
    </div>

    @if ($footer)
        <footer class="border-t border-border-soft px-4 py-3 text-xs text-secondary">
            {{ $footer }}
        </footer>
    @endif
</section>
