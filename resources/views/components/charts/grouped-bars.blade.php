{{--
    Two series side by side per period (e.g. داخل / خارج). Values are integer
    piastres. Oldest period on the right (RTL), value axis on the right, bar
    ends rounded only on the data side. Same numbers appear in the table view.
--}}
@props(['labels' => [], 'series' => [], 'height' => 182, 'alt' => ''])

@php
    use App\Casts\MoneyCast;

    $width = 600;
    $padLeft = 12;
    $padRight = 64;
    $padTop = 12;
    $padBottom = 26;
    $count = count($labels);
    $allValues = collect($series)->flatMap(fn ($s) => $s['values']);
    $empty = $count === 0 || ($allValues->max() === 0 && $allValues->min() === 0);

    $max = $empty ? 1 : max(1, $allValues->max());
    $plotHeight = $height - $padTop - $padBottom;
    $plotWidth = $width - $padRight - $padLeft;
    $groupWidth = $count ? $plotWidth / $count : $plotWidth;
    $barGap = 2;
    $barWidth = max(4, ($groupWidth * 0.6 - $barGap) / max(1, count($series)));
    $baseline = $height - $padBottom;
    $ticks = [$max, $max * 2 / 3, $max / 3, 0];

    $bars = [];
    foreach ($labels as $i => $label) {
        // Oldest period (index 0) goes on the right: walk from the right edge.
        $groupRight = ($width - $padRight) - $i * $groupWidth;
        $groupStart = $groupRight - $groupWidth * 0.8;

        foreach ($series as $s => $line) {
            $value = (int) ($line['values'][$i] ?? 0);
            $x = $groupStart + $s * ($barWidth + $barGap);
            $h = $value / $max * $plotHeight;
            $top = $baseline - $h;
            $r = min(4, $barWidth / 2, max($h, 0));

            // Rounded at the top (the data end), square at the baseline.
            $d = $h > 0
                ? 'M'.round($x, 2).' '.$baseline
                    .' V'.round($top + $r, 2)
                    .' Q'.round($x, 2).' '.round($top, 2).' '.round($x + $r, 2).' '.round($top, 2)
                    .' H'.round($x + $barWidth - $r, 2)
                    .' Q'.round($x + $barWidth, 2).' '.round($top, 2).' '.round($x + $barWidth, 2).' '.round($top + $r, 2)
                    .' V'.$baseline.' Z'
                : '';

            $bars[] = ['d' => $d, 'token' => $line['token'] ?? 'chart-a', 'label' => $label, 'name' => $line['name'], 'value' => $value];
        }
    }
@endphp

<div x-data="{ view: 'chart' }">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <ul class="flex gap-4 text-xs text-secondary">
            @foreach ($series as $line)
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-{{ $line['token'] ?? 'chart-a' }}" aria-hidden="true"></span>{{ $line['name'] }}
                </li>
            @endforeach
        </ul>
        <div class="flex gap-1 text-xs" role="group" aria-label="طريقة العرض">
            <button type="button" @click="view = 'chart'" :aria-pressed="view === 'chart'" :class="view === 'chart' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">رسم</button>
            <button type="button" @click="view = 'table'" :aria-pressed="view === 'table'" :class="view === 'table' ? 'bg-ink text-white' : 'text-secondary hover:bg-bg-subtle'" class="rounded-md px-2.5 py-1 font-medium">جدول</button>
        </div>
    </div>

    <div x-show="view === 'chart'">
        @if ($empty)
            <p class="py-10 text-center text-sm text-secondary">مفيش حركات في الفترة دي.</p>
        @else
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" role="img" aria-label="{{ $alt }}">
                @foreach ($ticks as $tick)
                    @php($y = round($baseline - $tick / $max * $plotHeight, 2))
                    <line x1="{{ $padLeft }}" x2="{{ $width - $padRight }}" y1="{{ $y }}" y2="{{ $y }}" class="stroke-chart-grid" stroke-width="1"/>
                    <text x="{{ $width - $padRight + 6 }}" y="{{ $y + 3 }}" class="fill-secondary text-[10px] tabular-nums">{{ MoneyCast::toDisplayString((int) round($tick)) }}</text>
                @endforeach

                @foreach ($bars as $bar)
                    @if ($bar['d'] !== '')
                        <path d="{{ $bar['d'] }}" class="fill-{{ $bar['token'] }}">
                            <title>{{ $bar['label'] }} — {{ $bar['name'] }}: {{ MoneyCast::toDisplayString($bar['value']) }} ج.م</title>
                        </path>
                    @endif
                @endforeach

                @foreach ($labels as $i => $label)
                    <text x="{{ round(($width - $padRight) - $i * $groupWidth - $groupWidth * 0.4, 2) }}" y="{{ $height - 8 }}" text-anchor="middle" class="fill-secondary text-[10px]">{{ $label }}</text>
                @endforeach
            </svg>
        @endif
    </div>

    <div x-show="view === 'table'" class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-xs text-secondary">
                    <th class="px-3 py-2 text-start font-semibold">الشهر</th>
                    @foreach ($series as $line)
                        <th class="px-3 py-2 text-end font-semibold">{{ $line['name'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft">
                @foreach ($labels as $i => $label)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $label }}</td>
                        @foreach ($series as $line)
                            <td class="px-3 py-2 text-end"><x-money :amount="(int) ($line['values'][$i] ?? 0)" /></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
