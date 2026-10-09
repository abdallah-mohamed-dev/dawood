<x-app-layout title="معاينة إقفال الموسم">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">معاينة إقفال الموسم</h1>

    @if ($data === null)
        <p class="text-secondary">مفيش موسم مفتوح دلوقتي.</p>
    @else
        <p class="mb-4 text-sm text-secondary">دي معاينة بس. ما بتتحفظش حاجة لحد ما تأكد الإقفال.</p>

        <form method="GET" action="{{ route('seasons.preview') }}" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label for="ends_at" class="mb-1 block text-xs font-medium text-ink-soft">تاريخ نهاية الموسم</label>
                <input id="ends_at" type="date" name="ends_at" value="{{ $data['ends_at'] }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm">
            </div>
            <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">حدّث المعاينة</button>
        </form>

        @if ($data['warnings']['owed_exceeds_cashbox'])
            <div class="mb-4 rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
                ⚠️ إجمالي المستحق للشركاء أكبر من رصيد الخزنة. الإقفال مش هيسحب فلوس، بس المستحق هيترحّل كالتزام.
            </div>
        @endif
        @if ($data['warnings']['loss_carried_out'])
            <div class="mb-4 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
                ⚠️ الموسم ده بيقفل بخسارة مُدوَّرة. مفيش توزيع فيه، والخسارة هتترحّل للموسم الجديد.
            </div>
        @endif

        <x-panel title="الربح والخسارة المُدوَّرة" class="mb-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>الإيراد: <x-money :amount="$data['revenue']" /></div>
                <div>تكلفة الخامات: <x-money :amount="$data['cost_of_materials']" /></div>
                <div>تكاليف الغرف: <x-money :amount="$data['room_costs']" /></div>
                <div>تكاليف الغرف الملغاة: <x-money :amount="$data['cancelled_room_costs']" /></div>
                <div>المصروفات الإدارية: <x-money :amount="$data['admin_expenses']" /></div>
                <div class="font-bold">صافي ربح الموسم: <x-money :amount="$data['net_profit']" /></div>
            </div>
            <p class="mt-4 text-sm text-ink-soft">
                الخسارة المُدوَّرة الداخلة <x-money :amount="$data['loss_carried_in']" />
                ⇐ القابل للتوزيع <x-money :amount="$data['distributable']" />
                ⇐ الخسارة المُدوَّرة الخارجة <x-money :amount="$data['loss_carried_out']" />
            </p>
        </x-panel>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-stat label="الغرف اللي هتتأرشف (مكتملة وملغاة)" size="sm">
                {{ $data['archived_rooms']['count'] }} غرفة — <x-money :amount="$data['archived_rooms']['value']" />
            </x-stat>
            <x-stat label="الغرف اللي هتترحّل (تحت التنفيذ ومسودة)" size="sm">
                {{ $data['wip']['count'] }} غرفة — تكلفتها المتراكمة <x-money :amount="$data['wip']['cost']" />
            </x-stat>
        </div>

        <div class="mb-6 overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-bg-subtle">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الشريك</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المُرحَّل</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">النصيب</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">المسحوب</th>
                        <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">المستحق المتبقي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($data['partners'] as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['name'] }}</td>
                            <td class="px-4 py-2 {{ $row['carried_in'] < 0 ? 'text-danger' : '' }}"><x-money :amount="$row['carried_in']" /></td>
                            <td class="px-4 py-2"><x-money :amount="$row['share']" /></td>
                            <td class="px-4 py-2"><x-money :amount="$row['withdrawn']" /></td>
                            <td class="px-4 py-2 text-end {{ $row['carried_out'] < 0 ? 'text-danger' : '' }}"><x-money :amount="$row['carried_out']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-panel>
            <p class="mb-3 text-sm text-ink-soft">
                الخزنة دلوقتي <x-money :amount="$data['cashbox_balance']" />. الباقي بيترحّل كما هو، والمخزون بقيمة <x-money :amount="$data['stock_value']" /> بيترحّل كمان.
            </p>
            <form method="POST" action="{{ route('seasons.close') }}" onsubmit="return confirm('تقفل الموسم ده؟ الإقفال مش بيتراجع تلقائي، ولازم تاخد نسخة احتياطية.');">
                @csrf
                <input type="hidden" name="ends_at" value="{{ $data['ends_at'] }}">
                <button type="submit" class="rounded-lg bg-danger px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90">أكّد إقفال الموسم</button>
                <a href="{{ route('seasons.index') }}" class="ms-3 text-sm text-secondary hover:underline">رجوع</a>
            </form>
        </x-panel>
    @endif
</x-app-layout>
