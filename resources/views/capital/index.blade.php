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

    <x-data-table :headings="['البند', 'السعر', 'التاريخ', 'ملاحظات', 'إجراءات']" :rows="$items">
        @foreach ($items as $item)
            <tr>
                <td class="px-4 py-2">{{ $item->name }}</td>
                <td class="px-4 py-2"><x-money :amount="$item->getRawOriginal('amount')" /></td>
                <td class="px-4 py-2">{{ $item->occurred_at->format('Y-m-d') }}</td>
                <td class="px-4 py-2">{{ $item->note ?? '—' }}</td>
                <td class="px-4 py-2 text-end">
                    <a href="{{ route('capital.edit', $item) }}" class="text-primary hover:underline">تعديل</a>
                    <form method="POST" action="{{ route('capital.destroy', $item) }}" class="inline" onsubmit="return confirm('تحذف البند ده؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ms-3 text-danger hover:underline">حذف</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $items->links() }}
    </div>

    <div class="mt-6 flex items-center justify-between rounded-xl border border-border bg-surface px-6 py-4 shadow-sm">
        <span class="text-base font-semibold text-ink">إجمالي رأس المال</span>
        <span class="text-xl font-bold text-primary"><x-money :amount="$total" /></span>
    </div>
    <p class="mt-2 text-xs text-secondary">رأس المال للتسجيل والعرض فقط ولا يدخل في رصيد الخزنة ولا في حساب الربح.</p>
</x-app-layout>
