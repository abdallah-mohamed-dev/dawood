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

    <x-data-table :headings="['الاسم', 'النسبة', 'النصيب', 'السحوبات', 'المتبقي', __('Actions')]" :rows="$rows">
        @foreach ($rows as $row)
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
                    <x-delete-button :action="route('partners.destroy', $row['partner'])" class="ms-3" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $partners->links() }}
    </div>
</x-app-layout>