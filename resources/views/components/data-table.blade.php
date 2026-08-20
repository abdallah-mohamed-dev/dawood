@props(['headings', 'rows', 'empty' => null])

<div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
    <table class="min-w-full divide-y divide-border text-sm">
        <thead class="bg-bg-subtle">
            <tr>
                @foreach ($headings as $index => $heading)
                    <th class="px-4 py-3 {{ $index === count($headings) - 1 ? 'text-end' : 'text-start' }} text-xs font-semibold uppercase tracking-wide text-secondary">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-border [&>tr:hover]:bg-bg-subtle">
            @if (count($rows) === 0)
                <tr>
                    <td colspan="{{ count($headings) }}" class="px-4 py-6 text-center text-secondary">{{ $empty ?? __('No results found.') }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>