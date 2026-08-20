<x-app-layout title="{{ $partner->name }}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $partner->name }}</h1>
            <p class="mt-1 text-sm text-secondary">نسبة الشريك: {{ $percentageDisplay }}%</p>
        </div>

        <a href="{{ route('partners.edit', $partner) }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
            {{ __('Edit') }}
        </a>
    </div>

    @if ($netProfit <= 0 || $remaining < 0)
        <div class="mb-6 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm font-medium text-warning shadow-sm">
            التحذير:
            @if ($netProfit <= 0)
                صافي الربح الحالي غير موجب، فلا يُحتسب نصيب للشريك حتى يتحقق ربح.
            @else
                سحوبات الشريك تجاوزت نصيبه الحالي (المتبقي سالب).
            @endif
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">صافي الربح الحالي</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$netProfit" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">نصيب الشريك</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$share" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">السحوبات</div>
            <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$withdrawn" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المتبقي</div>
            <div class="mt-1 text-xl font-bold {{ $remaining < 0 ? 'text-danger' : 'text-success' }}"><x-money :amount="$remaining" /></div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">إضافة سحب</h2>
        <form method="POST" action="{{ route('partners.withdrawals.store', $partner) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
                <input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('amount')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="occurred_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
                <input id="occurred_at" type="date" name="occurred_at" value="{{ now()->toDateString() }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('occurred_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="note" class="mb-1 block text-xs font-medium text-gray-700">ملاحظة</label>
                <input id="note" type="text" name="note" value="{{ old('note') }}" class="w-48 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
        </form>
    </div>

    <x-data-table :headings="['التاريخ', 'ملاحظة', 'المبلغ', __('Actions')]" :rows="$withdrawals">
        @foreach ($withdrawals as $withdrawal)
            <tr>
                <td class="px-4 py-2">{{ $withdrawal->occurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2 text-secondary">{{ $withdrawal->note ?? '—' }}</td>
                <td class="px-4 py-2"><x-money :amount="$withdrawal->amount" /></td>
                <td class="px-4 py-2 text-end">
                    <x-delete-button :action="route('partners.withdrawals.destroy', [$partner, $withdrawal])" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>