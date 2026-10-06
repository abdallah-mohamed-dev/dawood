@props(['headings', 'rows', 'empty' => null])

<div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="bg-bg-subtle">
            <tr>
                @foreach ($headings as $index => $heading)
                    <th class="px-4 py-2.5 {{ $index === count($headings) - 1 ? 'text-end' : 'text-start' }} text-[10.5px] font-semibold tracking-wide text-secondary">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-border-soft [&>tr:hover]:bg-bg-subtle">
            @if (count($rows) === 0)
                <tr>
                    <td colspan="{{ count($headings) }}" class="px-4 py-6 text-center text-secondary">{{ $empty ?? __('No results found.') }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
        @if (count($rows) > 0 && isset($footer))
            {{-- صف الإجماليات: بيظهر بس لما فيه صفوف، وبيتفصل بخط أغمق من فواصل الصفوف. --}}
            <tfoot class="border-t border-border bg-bg-subtle font-semibold text-ink">
                {{ $footer }}
            </tfoot>
        @endif
    </table>
</div>
