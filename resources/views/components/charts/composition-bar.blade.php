{{--
    One horizontal bar split into parts (e.g. sale price = materials + labor +
    other + profit). Values are integer piastres. Parts render in order,
    right to left (RTL). A single-hue ramp (token per part), never status
    colors. The table toggle lists the same parts and shares.
    :parts = list<array{label: string, value: int, token: string}>
--}}
@props(['parts' => [], 'total' => null, 'alt' => ''])

@php
    use App\Casts\MoneyCast;

    $total = $total ?? array_sum(array_column($parts, 'value'));
    $empty = $total <= 0;
@endphp

<div x-data="{ view: 'chart' }">
    <div class="mb-2 flex justify-end gap-1 text-xs" role="group" aria-label="طريقة العرض">
        <button type="button" @click="view = 'chart'" :aria-pressed="view === 'chart'" :class="view === 'chart' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">رسم</button>
        <button type="button" @click="view = 'table'" :aria-pressed="view === 'table'" :class="view === 'table' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">جدول</button>
    </div>

    <div x-show="view === 'chart'">
        @if ($empty)
            <p class="py-6 text-center text-sm text-secondary">مفيش سعر بيع لسه.</p>
        @else
            <div class="flex h-6 overflow-hidden rounded-lg" role="img" aria-label="{{ $alt }}">
                @foreach ($parts as $part)
                    @if ($part['value'] > 0)
                        <div
                            class="h-full bg-{{ $part['token'] }} {{ ! $loop->first ? 'border-e border-surface/60' : '' }}"
                            style="width: {{ round($part['value'] / $total * 100, 2) }}%"
                            title="{{ $part['label'] }}: {{ MoneyCast::toDisplayString($part['value']) }} ج.م"
                        ></div>
                    @endif
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-secondary">
                @foreach ($parts as $part)
                    <li class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-{{ $part['token'] }}" aria-hidden="true"></span>
                        {{ $part['label'] }}: <x-money :amount="$part['value']" />
                        <span class="tabular-nums">({{ $total > 0 ? round($part['value'] / $total * 100) : 0 }}%)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div x-show="view === 'table'" class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-xs text-secondary">
                    <th class="px-3 py-2 text-start font-semibold">البند</th>
                    <th class="px-3 py-2 text-end font-semibold">القيمة</th>
                    <th class="px-3 py-2 text-end font-semibold">النسبة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft">
                @foreach ($parts as $part)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $part['label'] }}</td>
                        <td class="px-3 py-2 text-end"><x-money :amount="$part['value']" /></td>
                        <td class="px-3 py-2 text-end tabular-nums">{{ $total > 0 ? round($part['value'] / $total * 100) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
