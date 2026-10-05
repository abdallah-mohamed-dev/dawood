<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">سعر البيع</div>
        <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['sale_price']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">تكلفة الخامات</div>
        <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['materials']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">التسعير التقديري</div>
        <div class="mt-1 text-xl font-bold text-gray-900">
            @if ($pricing['total']['estimated'] === null)
                —
            @else
                <x-money :amount="$pricing['total']['estimated']" />
            @endif
        </div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">مصروفات أخرى</div>
        <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['other']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">إجمالي التكلفة</div>
        <div class="mt-1 text-xl font-bold text-gray-900"><x-money :amount="$profit['total_cost']" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">{{ $room->status->countsTowardProfit() ? 'الربح' : 'الربح المتوقع' }}</div>
        <div class="mt-1 text-xl font-bold {{ $profit['profit'] < 0 ? 'text-danger' : 'text-success' }}">
            <x-money :amount="$profit['profit']" />
        </div>
        <p class="mt-1 text-xs text-secondary">
            لا يشمل المصروفات الإدارية.
            @unless ($room->status->countsTowardProfit())
                الخامات غير المصروفة لم تُحسب بعد.
            @endunless
        </p>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">المدفوع</div>
        <div class="mt-1 text-xl font-bold text-success"><x-money :amount="$room->paidAmount()" /></div>
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="text-sm text-secondary">المتبقي</div>
        <div class="mt-1 text-xl font-bold text-danger"><x-money :amount="$room->remainingAmount()" /></div>
    </div>
</div>
