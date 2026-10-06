{{--
    Revenue → net profit, each step either a running total or a change.
    Values are integer piastres. RTL: steps run right to left, oldest/first on
    the right. A "total" step draws full height from zero; a "change" step
    floats between the running total before and after it.
    :steps = list<array{label: string, value: int, type: 'total'|'change'}>
--}}
@props(['steps' => [], 'alt' => ''])

@php
    use App\Casts\MoneyCast;

    $width = 600;
    $padLeft = 12;
    $padRight = 64;
    $padTop = 16;
    $padBottom = 30;
    $count = count($steps);

    $running = 0;
    $bars = [];
    foreach ($steps as $step) {
        $before = $running;
        $running = $step['type'] === 'total' ? $step['value'] : $running + $step['value'];
        $bars[] = ['label' => $step['label'], 'from' => $before, 'to' => $running, 'type' => $step['type'], 'value' => $step['value']];
    }

    $allEdges = collect($bars)->flatMap(fn ($b) => [$b['from'], $b['to']]);
    $max = max(1, $allEdges->max());
    $min = min(0, $allEdges->min());
    $empty = $count === 0 || ($max === 0 && $min === 0);

    $plotHeight = 200;
    $plotWidth = $width - $padRight - $padLeft;
    $slot = $count ? $plotWidth / $count : $plotWidth;
    $barWidth = max(10, $slot * 0.6);
    $yAt = fn (int $value) => round($padTop + ($max - $value) / max(1, $max - $min) * $plotHeight, 2);
    $ticks = [$max, $min + ($max - $min) * 2 / 3, $min + ($max - $min) / 3, $min];
@endphp

<div x-data="{ view: 'chart' }">
    <div class="mb-2 flex justify-end gap-1 text-xs" role="group" aria-label="طريقة العرض">
        <button type="button" @click="view = 'chart'" :aria-pressed="view === 'chart'" :class="view === 'chart' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">رسم</button>
        <button type="button" @click="view = 'table'" :aria-pressed="view === 'table'" :class="view === 'table' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">جدول</button>
    </div>

    <div x-show="view === 'chart'">
        @if ($empty)
            <p class="py-10 text-center text-sm text-secondary">مفيش بيانات كفاية.</p>
        @else
            <svg viewBox="0 0 {{ $width }} {{ $padTop + $plotHeight + $padBottom }}" class="w-full" role="img" aria-label="{{ $alt }}">
                @foreach ($ticks as $tick)
                    <line x1="{{ $padLeft }}" x2="{{ $width - $padRight }}" y1="{{ $yAt((int) round($tick)) }}" y2="{{ $yAt((int) round($tick)) }}" class="stroke-chart-grid" stroke-width="1"/>
                    <text x="{{ $width - $padRight + 6 }}" y="{{ $yAt((int) round($tick)) + 3 }}" class="fill-secondary text-[10px] tabular-nums">{{ MoneyCast::toDisplayString((int) round($tick)) }}</text>
                @endforeach

                @foreach ($bars as $i => $bar)
                    @php
                        // Oldest/first step on the right.
                        $center = ($width - $padRight) - $i * $slot - $slot / 2;
                        $x = $center - $barWidth / 2;
                        $top = $yAt(max($bar['from'], $bar['to']));
                        $bottom = $yAt(min($bar['from'], $bar['to']));
                        $isTotal = $bar['type'] === 'total';
                        $token = $isTotal ? 'ramp-1' : ($bar['value'] >= 0 ? 'chart-a' : 'chart-b');
                    @endphp
                    <rect x="{{ round($x, 2) }}" y="{{ $top }}" width="{{ round($barWidth, 2) }}" height="{{ max(2, round($bottom - $top, 2)) }}" rx="3" class="fill-{{ $token }}">
                        <title>{{ $bar['label'] }}: {{ MoneyCast::toDisplayString($bar['value']) }} ج.م</title>
                    </rect>
                    <text x="{{ round($center, 2) }}" y="{{ $padTop + $plotHeight + 18 }}" text-anchor="middle" class="fill-secondary text-[10px]">{{ $bar['label'] }}</text>
                @endforeach
            </svg>
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
                @foreach ($bars as $bar)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $bar['label'] }}</td>
                        <td class="px-3 py-2 text-end"><x-money :amount="$bar['value']" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
