<x-app-layout title="الشركاء">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">الشركاء</h1>
        <a href="{{ route('partners.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    @if ($netProfit <= 0)
        <div class="mb-4 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm font-medium text-warning shadow-sm">
            صافي الربح الحالي غير موجب — لا يُحتسب نصيب أي شريك حتى يتحقق ربح.
        </div>
    @endif

    @if ($hasOverWithdrawal)
        <div class="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm font-medium text-danger shadow-sm">
            يوجد شريك تجاوزت سحوباته نصيبه الحالي (متبقي سالب).
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-bg-subtle">
                <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الاسم</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">النسبة</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">النصيب</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">السحوبات</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المتبقي</th>
                    <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border [&>tr:hover]:bg-bg-subtle">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('partners.show', $row['partner']) }}" class="text-primary hover:underline">{{ $row['partner']->name }}</a>
                        </td>
                        <td class="px-4 py-2">{{ number_format($row['partner']->percentage / 100, 2) }}%</td>
                        <td class="px-4 py-2"><x-money :amount="$row['share']" /></td>
                        <td class="px-4 py-2"><x-money :amount="$row['withdrawn']" /></td>
                        <td class="px-4 py-2 {{ $row['remaining'] < 0 ? 'text-danger' : '' }}">
                            <x-money :amount="$row['remaining']" />
                        </td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('partners.edit', $row['partner']) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('partners.destroy', $row['partner']) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $partners->links() }}
    </div>
</x-app-layout>