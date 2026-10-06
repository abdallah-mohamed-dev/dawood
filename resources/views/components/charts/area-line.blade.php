{{--
    Running balance over time. Values are integer piastres, oldest first.
    RTL: the oldest point sits on the right and the newest on the left.
    The value axis is on the right. Every point has a native tooltip, and the
    table toggle shows the same numbers as text.
--}}
@props(['labels' => [], 'values' => [], 'height' => 196, 'alt' => ''])

@php
    use App\Casts\MoneyCast;

    $width = 600;
    $padLeft = 16;
    $padRight = 64;
    $padTop = 12;
    $padBottom = 26;
    $count = count($values);
    $empty = $count === 0 || (max($values) === 0 && min($values) === 0);

    $min = $empty ? 0 : min(0, min($values));
    $max = $empty ? 1 : max(max($values), $min + 1);
    $plotHeight = $height - $padTop - $padBottom;
    $plotWidth = $width - $padRight - $padLeft;

    $xAt = fn (int $i) => $count > 1
        ? round(($width - $padRight) - $i * $plotWidth / ($count - 1), 2)
        : round($width - $padRight, 2);
    $yAt = fn (int $value) => round($padTop + ($max - $value) / ($max - $min) * $plotHeight, 2);

    $points = [];
    foreach ($values as $i => $value) {
        $points[] = ['x' => $xAt($i), 'y' => $yAt($value), 'label' => $labels[$i] ?? '', 'value' => $value];
    }
    $linePath = $points ? 'M'.implode(' L', array_map(fn ($p) => $p['x'].' '.$p['y'], $points)) : '';
    $areaPath = $points
        ? $linePath.' L'.end($points)['x'].' '.($height - $padBottom).' L'.$points[0]['x'].' '.($height - $padBottom).' Z'
        : '';
    $ticks = [$max, $min + ($max - $min) * 2 / 3, $min + ($max - $min) / 3, $min];
@endphp

<div x-data="{ view: 'chart' }">
    <div class="mb-2 flex justify-end gap-1 text-xs" role="group" aria-label="طريقة العرض">
        <button type="button" @click="view = 'chart'" :aria-pressed="view === 'chart'" :class="view === 'chart' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">رسم</button>
        <button type="button" @click="view = 'table'" :aria-pressed="view === 'table'" :class="view === 'table' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">جدول</button>
    </div>

    <div x-show="view === 'chart'">
        @if ($empty)
            <p class="py-10 text-center text-sm text-secondary">مفيش حركات في الفترة دي.</p>
        @else
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" role="img" aria-label="{{ $alt }}">
                @foreach ($ticks as $tick)
                    <line x1="{{ $padLeft }}" x2="{{ $width - $padRight }}" y1="{{ $yAt((int) round($tick)) }}" y2="{{ $yAt((int) round($tick)) }}" class="stroke-chart-grid" stroke-width="1"/>
                    <text x="{{ $width - $padRight + 6 }}" y="{{ $yAt((int) round($tick)) + 3 }}" class="fill-secondary text-[10px] tabular-nums">{{ MoneyCast::toDisplayString((int) round($tick)) }}</text>
                @endforeach

                <path d="{{ $areaPath }}" class="fill-chart-a/10"/>
                <path d="{{ $linePath }}" fill="none" class="stroke-chart-a" stroke-width="2" stroke-linejoin="round"/>

                @foreach ($points as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" class="fill-chart-a stroke-surface" stroke-width="1.5">
                        <title>{{ $point['label'] }}: {{ MoneyCast::toDisplayString($point['value']) }} ج.م</title>
                    </circle>
                    <text x="{{ $point['x'] }}" y="{{ $height - 8 }}" text-anchor="middle" class="fill-secondary text-[10px]">{{ $point['label'] }}</text>
                @endforeach
            </svg>
        @endif
    </div>

    <div x-show="view === 'table'" class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-xs text-secondary">
                    <th class="px-3 py-2 text-start font-semibold">الشهر</th>
                    <th class="px-3 py-2 text-end font-semibold">الرصيد</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft">
                @foreach ($points as $point)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $point['label'] }}</td>
                        <td class="px-3 py-2 text-end"><x-money :amount="$point['value']" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
