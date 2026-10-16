{{--
    On a phone each row is the label and figure on one line, with the bar
    full width under them; from sm up it is one line of three columns.

    Revenue → net profit as plain bars that all start from zero: revenue on
    top, each deduction under it at its own size (with its share of revenue),
    and net profit last. Replaces a floating waterfall, whose mid-air bars
    were hard to read. Drawn in HTML so it reads the same on a phone.

    A deduction of zero (e.g. no cancelled rooms) is left out of the bars —
    the table view still lists it. Values are whole pounds.

    :revenue int · :deductions list<array{label: string, value: int}> (positive) · :net int
--}}
@props(['revenue' => 0, 'deductions' => [], 'net' => 0, 'alt' => ''])

@php
    $visible = collect($deductions)->filter(fn (array $d) => (int) $d['value'] !== 0)->values();
    $scale = max(1, abs($revenue), abs($net), (int) $visible->max(fn (array $d) => abs($d['value'])));
    $empty = $revenue === 0 && $net === 0 && $visible->isEmpty();
    $width = fn (int $value) => round(abs($value) / $scale * 100, 2);
    $share = fn (int $value) => $revenue > 0 ? (int) round(abs($value) * 100 / $revenue) : null;
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
                <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 sm:grid-cols-[minmax(0,7rem)_1fr_minmax(0,9rem)] text-sm">
                    <span class="font-semibold text-ink">الإيراد</span>
                    <span class="order-3 col-span-2 h-3 overflow-hidden rounded-full bg-bg-subtle sm:order-2 sm:col-span-1" aria-hidden="true">
                        <span class="block h-full rounded-full bg-chart-a" style="width: {{ $width($revenue) }}%"></span>
                    </span>
                    <span class="order-2 text-end font-semibold tabular-nums text-ink sm:order-3"><x-money :amount="$revenue" /></span>
                </li>

                @foreach ($visible as $deduction)
                    <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 sm:grid-cols-[minmax(0,7rem)_1fr_minmax(0,9rem)] text-sm">
                        <span class="truncate text-ink-soft" title="{{ $deduction['label'] }}">{{ $deduction['label'] }}</span>
                        <span class="order-3 col-span-2 h-3 overflow-hidden rounded-full bg-bg-subtle sm:order-2 sm:col-span-1" aria-hidden="true">
                            <span class="block h-full rounded-full bg-chart-b" style="width: {{ $width($deduction['value']) }}%"></span>
                        </span>
                        <span class="order-2 text-end tabular-nums text-ink-soft sm:order-3">
                            − <x-money :amount="abs($deduction['value'])" />
                            @if ($share($deduction['value']) !== null)
                                <span class="text-xs text-secondary">({{ $share($deduction['value']) }}٪)</span>
                            @endif
                        </span>
                    </li>
                @endforeach

                <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 sm:grid-cols-[minmax(0,7rem)_1fr_minmax(0,9rem)] border-t border-border-soft pt-3 text-sm">
                    <span class="font-semibold text-ink">صافي الربح</span>
                    <span class="order-3 col-span-2 h-3 overflow-hidden rounded-full bg-bg-subtle sm:order-2 sm:col-span-1" aria-hidden="true">
                        <span class="block h-full rounded-full {{ $net < 0 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $width($net) }}%"></span>
                    </span>
                    <span class="order-2 text-end font-semibold tabular-nums sm:order-3 {{ $net < 0 ? 'text-danger' : 'text-success' }}">
                        <x-money :amount="$net" />
                        @if ($share($net) !== null)
                            <span class="text-xs font-normal text-secondary">({{ $share($net) }}٪)</span>
                        @endif
                    </span>
                </li>
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
                <tr>
                    <td class="px-3 py-2 text-start">الإيراد</td>
                    <td class="px-3 py-2 text-end"><x-money :amount="$revenue" /></td>
                </tr>
                @foreach ($deductions as $deduction)
                    <tr>
                        <td class="px-3 py-2 text-start">{{ $deduction['label'] }}</td>
                        <td class="px-3 py-2 text-end">− <x-money :amount="abs($deduction['value'])" /></td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="px-3 py-2 text-start">صافي الربح</td>
                    <td class="px-3 py-2 text-end"><x-money :amount="$net" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
