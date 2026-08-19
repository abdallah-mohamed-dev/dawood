<x-app-layout title="الخزنة">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">الخزنة</h1>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">الرصيد الحالي</div>
            <div class="mt-1 text-2xl font-bold {{ $balance < 0 ? 'text-danger' : 'text-gray-900' }}">
                <x-money :amount="$balance" />
            </div>
        </div>
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">إجمالي الداخل</div>
            <div class="mt-1 text-2xl font-bold text-success"><x-money :amount="$totalIn" /></div>
        </div>
        <div class="rounded-lg border border-border bg-surface p-4">
            <div class="text-sm text-secondary">إجمالي الخارج</div>
            <div class="mt-1 text-2xl font-bold text-danger"><x-money :amount="$totalOut" /></div>
        </div>
    </div>

    <div class="mb-6 rounded-lg border border-border bg-surface p-4">
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
                    class="w-40 rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
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
                    class="rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                >
                @error('occurred_at')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Save') }}
            </button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-secondary">التاريخ</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">النوع</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">البند</th>
                    <th class="px-4 py-2 text-start font-medium text-secondary">الوصف</th>
                    <th class="px-4 py-2 text-end font-medium text-secondary">المبلغ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($transactions as $transaction)
                    <tr>
                        <td class="px-4 py-2">{{ $transaction->occurred_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            <span class="{{ $transaction->type === \App\Enums\CashboxTransactionType::In ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->type->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $transaction->kind->label() }}</td>
                        <td class="px-4 py-2 text-secondary">{{ $transaction->description ?? '—' }}</td>
                        <td class="px-4 py-2 text-end">
                            <x-money :amount="$transaction->amount" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $transactions->links() }}
    </div>
</x-app-layout>
