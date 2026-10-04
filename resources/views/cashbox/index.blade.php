<x-app-layout title="الخزنة">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">الخزنة</h1>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">الرصيد الحالي</div>
            <div class="mt-1 text-2xl font-bold {{ $balance < 0 ? 'text-danger' : 'text-gray-900' }}">
                <x-money :amount="$balance" />
            </div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إجمالي الداخل</div>
            <div class="mt-1 text-2xl font-bold text-success"><x-money :amount="$totalIn" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إجمالي الخارج</div>
            <div class="mt-1 text-2xl font-bold text-danger"><x-money :amount="$totalOut" /></div>
        </div>
    </div>

    {{-- The whole box links to the debts page. Debts are not part of the balance above. --}}
    <a href="{{ route('debts.index') }}" class="mb-6 block rounded-xl border border-border bg-surface p-4 shadow-sm transition-colors hover:bg-bg-subtle">
        <div class="text-sm text-secondary">إجمالي الديون القائمة</div>
        <div class="mt-1 text-2xl font-bold text-gray-900"><x-money :amount="$debtsOutstanding" /></div>
        <p class="mt-2 text-xs text-secondary">الديون للتسجيل والتذكير فقط ولا تدخل في رصيد الخزنة.</p>
    </a>

    <div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">الرصيد الافتتاحي</h2>

        <form method="POST" action="{{ route('cashbox.opening-balance.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf

            <div>
                <label for="amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
                <input
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0"
                    name="amount"
                    value="{{ old('amount', $openingBalance?->amount) }}"
                    required
                    class="w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                @error('amount')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="occurred_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
                <input
                    id="occurred_at"
                    type="date"
                    name="occurred_at"
                    value="{{ old('occurred_at', $openingBalance?->occurred_at?->toDateString() ?? now()->toDateString()) }}"
                    required
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                @error('occurred_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{--
                Hiding the method is visual only. x-show keeps the <select> in the
                DOM so it is still submitted — the opening balance is always
                recorded with a method, even while the field is hidden.
            --}}
            <div x-data="persistedToggle('cashbox.opening.paymentMethod.visible')" class="flex items-end gap-2">
                <div x-show="open">
                    <x-payment-method-select :selected="$openingBalance?->payment_method" />
                </div>

                <button type="button" @click="toggle()" class="rounded-lg border border-border px-3 py-2 text-xs font-medium text-gray-700 hover:bg-bg" x-text="open ? 'إخفاء طريقة الدفع' : 'إظهار طريقة الدفع'"></button>
            </div>

            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
        </form>
    </div>

    <div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm" x-data="persistedToggle('cashbox.breakdown.visible')">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-900">التقسيم حسب طريقة الدفع</h2>
            <button type="button" @click="toggle()" class="text-xs font-medium text-primary hover:underline" x-text="open ? 'إخفاء' : 'إظهار'"></button>
        </div>

        <div x-show="open">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($methods as $method)
                    @php $row = $breakdown[$method->value] ?? ['in' => 0, 'out' => 0]; @endphp
                    <div class="rounded-lg border border-border bg-bg-subtle p-3">
                        <div class="text-xs font-semibold text-gray-900">{{ $method->label() }}</div>
                        <div class="mt-2 text-xs text-secondary">
                            داخل: <span class="font-semibold text-success"><x-money :amount="$row['in']" /></span>
                        </div>
                        <div class="mt-1 text-xs text-secondary">
                            خارج: <span class="font-semibold text-danger"><x-money :amount="$row['out']" /></span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (! empty($breakdown['unknown']))
                <div class="mt-3 rounded-lg border border-border bg-bg-subtle p-3 text-xs text-secondary">
                    حركات قديمة بدون طريقة دفع مسجَّلة —
                    داخل: <x-money :amount="$breakdown['unknown']['in']" /> ·
                    خارج: <x-money :amount="$breakdown['unknown']['out']" />
                </div>
            @endif

            <p class="mt-3 text-xs text-secondary">
                التقسيم للعرض فقط. رصيد الخزنة رقم واحد، وهذه ليست محافظ منفصلة بأرصدة مستقلة.
            </p>
        </div>
    </div>

    @php
        $cashboxHeadings = ['التاريخ', 'البند', 'طريقة الدفع', 'المبلغ'];
        $currentInMonth = null;
        $currentOutMonth = null;
    @endphp

    {{--
        Month separators follow the expenses page: one row per calendar month
        change, with the total from the controller's month query (over every
        row of that direction), not from summing the rows on this page.
    --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div>
            <h2 class="mb-3 text-sm font-semibold text-success">الداخل</h2>

            <x-data-table :headings="$cashboxHeadings" :rows="$incoming" empty="لا توجد حركات داخلة.">
                @foreach ($incoming as $transaction)
                    @php $rowMonthKey = $transaction->occurred_at->format('Y-m'); @endphp

                    @if ($rowMonthKey !== $currentInMonth)
                        @php $currentInMonth = $rowMonthKey; @endphp
                        <tr class="bg-bg-subtle">
                            <td colspan="{{ count($cashboxHeadings) }}" class="px-4 py-2 text-xs font-semibold text-secondary">
                                {{ __('date.months.'.$transaction->occurred_at->month) }} {{ $transaction->occurred_at->year }}
                                — إجمالي الشهر: <x-money :amount="(int) ($incomingMonthlyTotals[$rowMonthKey] ?? 0)" />
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $transaction->occurred_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            {{ $transaction->detailedLabel() }}
                            @if ($transaction->description)
                                <span class="block text-xs text-secondary">{{ $transaction->description }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-secondary whitespace-nowrap">{{ $transaction->payment_method?->label() ?? '—' }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap text-success">
                            <x-money :amount="$transaction->amount" />
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            <div class="mt-4">
                {{ $incoming->links() }}
            </div>
        </div>

        <div>
            <h2 class="mb-3 text-sm font-semibold text-danger">الخارج</h2>

            <x-data-table :headings="$cashboxHeadings" :rows="$outgoing" empty="لا توجد حركات خارجة.">
                @foreach ($outgoing as $transaction)
                    @php $rowMonthKey = $transaction->occurred_at->format('Y-m'); @endphp

                    @if ($rowMonthKey !== $currentOutMonth)
                        @php $currentOutMonth = $rowMonthKey; @endphp
                        <tr class="bg-bg-subtle">
                            <td colspan="{{ count($cashboxHeadings) }}" class="px-4 py-2 text-xs font-semibold text-secondary">
                                {{ __('date.months.'.$transaction->occurred_at->month) }} {{ $transaction->occurred_at->year }}
                                — إجمالي الشهر: <x-money :amount="(int) ($outgoingMonthlyTotals[$rowMonthKey] ?? 0)" />
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $transaction->occurred_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            {{ $transaction->detailedLabel() }}
                            @if ($transaction->description)
                                <span class="block text-xs text-secondary">{{ $transaction->description }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-secondary whitespace-nowrap">{{ $transaction->payment_method?->label() ?? '—' }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap text-danger">
                            <x-money :amount="$transaction->amount" />
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            <div class="mt-4">
                {{ $outgoing->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
