<x-app-layout title="تقرير الربح">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تقرير الربح</h1>

    <div class="mb-6 rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm text-gray-700">
        <strong class="text-gray-900">رصيد الخزنة</strong> و<strong class="text-gray-900">صافي الربح</strong> رقمان مختلفان تمامًا ولا يجب الخلط بينهما:
        رصيد الخزنة نقدي (كل جنيه دخل أو خرج فعليًا)، بينما صافي الربح محاسبي (إيراد وتكلفة الغرف المكتملة فقط + كل المصروفات).
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">رصيد الخزنة (نقدي)</div>
            <div class="mt-1 text-2xl font-bold {{ $cashboxBalance < 0 ? 'text-danger' : 'text-gray-900' }}">
                <x-money :amount="$cashboxBalance" />
            </div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">صافي الربح (استحقاقي)</div>
            <div class="mt-1 text-2xl font-bold {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">
                <x-money :amount="$netProfit" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">الإيراد (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$revenue" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكلفة الخامات (الغرف المكتملة)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$costOfMaterials" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف الغرف المكتملة (مصنعية + أخرى)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$roomCosts" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">تكاليف غرف ملغاة (خسارة)</div>
            <div class="mt-1 text-lg font-semibold {{ $cancelledRoomCosts > 0 ? 'text-danger' : 'text-gray-900' }}">
                <x-money :amount="$cancelledRoomCosts" />
            </div>
            <div class="mt-1 text-xs text-secondary">مصنعية ومصروفات غرف ألغيت — فلوس خرجت ولن تعود، فتُخصم فورًا.</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">المصروفات الإدارية</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$adminExpenses" /></div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">إنتاج تحت التشغيل (WIP)</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$workInProgress" /></div>
            <div class="mt-1 text-xs text-secondary">خامات وتكاليف غرف لم تكتمل بعد — أصل، ليست تكلفة.</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-sm text-secondary">قيمة المخزون غير المصروف</div>
            <div class="mt-1 text-lg font-semibold text-gray-900"><x-money :amount="$stockValue" /></div>
            <div class="mt-1 text-xs text-secondary">خامات مشتراة ولم تُصرف بعد — أصل، ليست تكلفة.</div>
        </div>
    </div>
</x-app-layout>
