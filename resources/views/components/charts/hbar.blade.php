{{--
    Horizontal bars for ranked lists (top materials, stock by type). Values are
    integer piastres. Bar length is relative to the largest value. The table
    toggle shows the same numbers. Drawn with HTML so long names wrap cleanly.
--}}
@props(['items' => [], 'alt' => ''])

@php
    use App\Casts\MoneyCast;

    $max = collect($items)->max('value') ?? 0;
    $empty = count($items) === 0 || $max === 0;
@endphp

<div x-data="{ view: 'chart' }">
    <div class="mb-3 flex justify-end gap-1 text-xs" role="group" aria-label="طريقة العرض">
        <button type="button" @click="view = 'chart'" :aria-pressed="view === 'chart'" :class="view === 'chart' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">رسم</button>
        <button type="button" @click="view = 'table'" :aria-pressed="view === 'table'" :class="view === 'table' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">جدول</button>
    </div>

    <div x-show="view === 'chart'">
        @if ($empty)
            <p class="py-8 text-center text-sm text-secondary">مفيش بيانات في الفترة دي.</p>
        @else
            <ul class="space-y-3" role="img" aria-label="{{ $alt }}">
                @foreach ($items as $item)
                    <li class="grid grid-cols-[minmax(0,7rem)_1fr_auto] items-center gap-3 text-sm">
                        <span class="truncate text-ink-soft" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                        <span class="h-2 overflow-hidden rounded-full bg-bg-subtle" aria-hidden="true">
                            <span class="block h-full rounded-full bg-chart-a" style="width: {{ round($item['value'] / $max * 100, 2) }}%"></span>
                        </span>
                        <span class="tabular-nums text-ink"><x-money :amount="$item['value']" /></span>
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
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft">
                @foreach ($items as $item)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $item['label'] }}</td>
                        <td class="px-3 py-2 text-end"><x-money :amount="$item['value']" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
