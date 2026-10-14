<x-app-layout title="رأس المال">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">رأس المال</h1>

    <x-quick-add :action="route('capital.store')" title="إضافة بند">
        <x-quick-field name="name" label="البند" width="w-56" required />
        <x-quick-field name="amount" label="السعر (ج.م)" type="number" step="1" min="0" width="w-40" required />
        <x-quick-field name="occurred_at" label="التاريخ" type="date" width="w-44" required />
        <x-quick-field name="note" label="ملاحظات" width="w-64" />
    </x-quick-add>

    <form method="GET" action="{{ route('capital.index') }}" class="mb-6 flex flex-wrap items-end gap-2 border-b border-border pb-4">
        <div class="relative w-full max-w-xs">
            <input type="search" name="q" value="{{ $search }}" placeholder="ابحث بالبند أو الملاحظة" class="w-full rounded-full border border-transparent bg-bg-subtle py-2 px-4 text-sm text-ink focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-soft">من تاريخ</label>
            <input id="from" type="date" name="from" value="{{ $from }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-soft">إلى تاريخ</label>
            <input id="to" type="date" name="to" value="{{ $to }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">بحث</button>
        @if ($search !== '' || $from !== '' || $to !== '')
            <a href="{{ route('capital.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">إلغاء الفلاتر</a>
        @endif
    </form>

    {{-- The row whose save just failed comes back open (see x-edit-row). --}}
    @php $reopenId = (int) old('editing_id', 0); @endphp

    <div x-data="{ editing: {{ $reopenId }} }">
        <x-data-table :headings="['البند', 'السعر', 'التاريخ', 'ملاحظات', 'إجراءات']" :rows="$items">
            @foreach ($items as $item)
                @php
                    $reopen = $reopenId === $item->id;
                    $bag = $reopen ? 'row' : 'none';
                @endphp
                <tr>
                    <td class="px-4 py-2">{{ $item->name }}</td>
                    <td class="px-4 py-2"><x-money :amount="$item->getRawOriginal('amount')" /></td>
                    <td class="px-4 py-2">{{ $item->occurred_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-2">{{ $item->note ?? '—' }}</td>
                    <td class="px-4 py-2 text-end whitespace-nowrap">
                        <button type="button" x-on:click="editing = {{ $item->id }}" class="text-primary hover:underline">تعديل</button>
                        <form method="POST" action="{{ route('capital.destroy', $item) }}" class="inline" onsubmit="return confirm('تحذف البند ده؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ms-3 text-danger hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                <x-edit-row :action="route('capital.update', $item)" :key="$item->id" colspan="5">
                    <x-quick-field name="name" :id="'name_'.$item->id" label="البند" width="w-56" :bag="$bag" :value="$reopen ? old('name', '') : $item->name" required />
                    <x-quick-field name="amount" :id="'amount_'.$item->id" label="السعر (ج.م)" type="number" step="1" min="0" width="w-40" :bag="$bag" :value="$reopen ? old('amount', '') : $item->amount" required />
                    <x-quick-field name="occurred_at" :id="'occurred_at_'.$item->id" label="التاريخ" type="date" width="w-44" :bag="$bag" :value="$reopen ? old('occurred_at', '') : $item->occurred_at->format('Y-m-d')" required />
                    <x-quick-field name="note" :id="'note_'.$item->id" label="ملاحظات" width="w-64" :bag="$bag" :value="$reopen ? old('note', '') : ($item->note ?? '')" />
                </x-edit-row>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>

    <div class="mt-6 flex items-center justify-between rounded-xl border border-border bg-surface px-6 py-4 shadow-sm">
        <span class="text-base font-semibold text-ink">إجمالي رأس المال</span>
        <span class="text-xl font-bold text-primary"><x-money :amount="$total" /></span>
    </div>
    <p class="mt-2 text-xs text-secondary">رأس المال للتسجيل والعرض فقط ولا يدخل في رصيد الخزنة ولا في حساب الربح.</p>
</x-app-layout>
