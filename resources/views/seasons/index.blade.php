<x-app-layout title="المواسم">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-ink">المواسم</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('seasons.preview') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">معاينة إقفال الموسم</a>
            @if ($canReopen)
                <form method="POST" action="{{ route('seasons.reopen', $latestClosed) }}" onsubmit="return confirm('تفتح الموسم ده تاني؟ الموسم الجديد الفاضي هيتمسح.');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">فتح الموسم</button>
                </form>
            @endif
        </div>
    </div>

    @if ($openSeason)
        <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm">
            الموسم المفتوح دلوقتي: <strong>{{ $names[$openSeason->id] }}</strong>
        </div>
    @endif

    <x-data-table :headings="['الموسم', 'صافي الربح', 'الخسارة المُدوَّرة الداخلة', 'القابل للتوزيع', 'الموزَّع على الشركاء', 'الخسارة المُدوَّرة الخارجة', 'الغرف المؤرشفة']" :rows="$seasons">
        @foreach ($seasons as $season)
            <tr class="{{ $selected?->id === $season->id ? 'bg-bg-subtle' : '' }}">
                <td class="px-4 py-2">
                    <a href="{{ route('seasons.index', ['season' => $season->id]) }}" class="text-primary hover:underline">{{ $names[$season->id] }}</a>
                    @if ($season->status === \App\Enums\SeasonStatus::Open)
                        <span class="ms-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">مفتوح</span>
                    @endif
                </td>
                @if ($season->status === \App\Enums\SeasonStatus::Closed)
                    <td class="px-4 py-2 {{ $season->getRawOriginal('net_profit') < 0 ? 'text-danger' : '' }}"><x-money :amount="$season->getRawOriginal('net_profit')" /></td>
                    <td class="px-4 py-2"><x-money :amount="$season->getRawOriginal('loss_carried_in')" /></td>
                    <td class="px-4 py-2"><x-money :amount="$season->getRawOriginal('distributable_profit')" /></td>
                    <td class="px-4 py-2"><x-money :amount="(int) ($distributed[$season->id] ?? 0)" /></td>
                    <td class="px-4 py-2"><x-money :amount="$season->getRawOriginal('loss_carried_out')" /></td>
                @else
                    <td class="px-4 py-2 text-secondary" colspan="5">يتحسب عند الإقفال</td>
                @endif
                <td class="px-4 py-2">{{ $archived[$season->id] ?? 0 }}</td>
            </tr>
        @endforeach
    </x-data-table>

    @if ($selected && $selected->status === \App\Enums\SeasonStatus::Closed)
        <x-panel title="تفاصيل {{ $names[$selected->id] }}" class="mt-8">

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div><span class="text-sm text-secondary">الإيراد</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('revenue')" /></div></div>
                <div><span class="text-sm text-secondary">تكلفة الخامات</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('cost_of_materials')" /></div></div>
                <div><span class="text-sm text-secondary">تكاليف الغرف</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('room_costs')" /></div></div>
                <div><span class="text-sm text-secondary">تكاليف الغرف الملغاة</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('cancelled_room_costs')" /></div></div>
                <div><span class="text-sm text-secondary">المصروفات الإدارية</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('admin_expenses')" /></div></div>
                <div><span class="text-sm text-secondary">صافي الربح</span> <div class="font-bold"><x-money :amount="$selected->getRawOriginal('net_profit')" /></div></div>
            </div>

            <p class="mb-4 text-sm text-ink-soft">
                الخسارة المُدوَّرة الداخلة <x-money :amount="$selected->getRawOriginal('loss_carried_in')" />
                ⇐ القابل للتوزيع <x-money :amount="$selected->getRawOriginal('distributable_profit')" />
                ⇐ الخسارة المُدوَّرة الخارجة <x-money :amount="$selected->getRawOriginal('loss_carried_out')" />
            </p>

            <x-data-table :headings="['الشريك', 'النسبة وقت الإقفال', 'المُرحَّل', 'النصيب', 'المسحوب', 'المُرحَّل للموسم الجديد']" :rows="$shares">
                @foreach ($shares as $share)
                    <tr>
                        <td class="px-4 py-2">{{ $share->partner->name }}</td>
                        <td class="px-4 py-2">{{ number_format($share->percentage / 100, 2) }}%</td>
                        <td class="px-4 py-2 {{ $share->getRawOriginal('carried_in') < 0 ? 'text-danger' : '' }}"><x-money :amount="$share->getRawOriginal('carried_in')" /></td>
                        <td class="px-4 py-2"><x-money :amount="$share->getRawOriginal('share_amount')" /></td>
                        <td class="px-4 py-2"><x-money :amount="$share->getRawOriginal('withdrawn')" /></td>
                        <td class="px-4 py-2 {{ $share->getRawOriginal('carried_out') < 0 ? 'text-danger' : '' }}"><x-money :amount="$share->getRawOriginal('carried_out')" /></td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-panel>
    @endif
</x-app-layout>
