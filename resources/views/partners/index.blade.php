<x-app-layout title="الشركاء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">الشركاء</h1>

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

    <x-quick-add :action="route('partners.store')" title="إضافة شريك">
        <x-quick-field name="name" label="الاسم" width="w-56" required />
        <x-quick-field name="email" type="email" label="البريد الإلكتروني (اختياري)" width="w-56" />
        <x-quick-field name="percentage" type="number" step="0.01" min="0" label="النسبة المئوية (%)" width="w-32" placeholder="مثال: 33.33" required />
    </x-quick-add>

    {{-- The row whose save just failed comes back open (see x-edit-row). --}}
    @php $reopenId = (int) old('editing_id', 0); @endphp

    <div x-data="{ editing: {{ $reopenId }} }">
        <x-data-table :headings="['الاسم', 'البريد الإلكتروني', 'النسبة', 'المُرحَّل', 'النصيب', 'السحوبات', 'المتبقي', __('Actions')]" :rows="$rows">
            @foreach ($rows as $row)
                @php
                    $partner = $row['partner'];
                    $reopen = $reopenId === $partner->id;
                    $bag = $reopen ? 'row' : 'none';
                @endphp
                <tr>
                    <td class="px-4 py-2">
                        <a href="{{ route('partners.show', $partner) }}" class="text-primary hover:underline">{{ $partner->name }}</a>
                    </td>
                    <td class="px-4 py-2">{{ $partner->email ?? '—' }}</td>
                    <td class="px-4 py-2">{{ number_format($partner->percentage / 100, 2) }}%</td>
                    <td class="px-4 py-2 {{ $row['carried_in'] < 0 ? 'text-danger' : '' }}"><x-money :amount="$row['carried_in']" /></td>
                    <td class="px-4 py-2"><x-money :amount="$row['share']" /></td>
                    <td class="px-4 py-2"><x-money :amount="$row['withdrawn']" /></td>
                    <td class="px-4 py-2 {{ $row['remaining'] < 0 ? 'text-danger' : '' }}">
                        <x-money :amount="$row['remaining']" />
                    </td>
                    <td class="px-4 py-2 text-end whitespace-nowrap">
                        <button type="button" x-on:click="editing = {{ $partner->id }}" class="text-primary hover:underline">{{ __('Edit') }}</button>
                        <x-delete-button :action="route('partners.destroy', $partner)" class="ms-3" />
                    </td>
                </tr>
                <x-edit-row :action="route('partners.update', $partner)" :key="$partner->id" colspan="8">
                    <x-quick-field name="name" :id="'name_'.$partner->id" label="الاسم" width="w-56" :bag="$bag" :value="$reopen ? old('name', '') : $partner->name" required />
                    <x-quick-field name="email" :id="'email_'.$partner->id" type="email" label="البريد الإلكتروني (اختياري)" width="w-56" :bag="$bag" :value="$reopen ? old('email', '') : ($partner->email ?? '')" />
                    <x-quick-field name="percentage" :id="'percentage_'.$partner->id" type="number" step="0.01" min="0" label="النسبة المئوية (%)" width="w-32" :bag="$bag" :value="$reopen ? old('percentage', '') : number_format($partner->percentage / 100, 2)" required />
                </x-edit-row>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $partners->links() }}
    </div>
</x-app-layout>
