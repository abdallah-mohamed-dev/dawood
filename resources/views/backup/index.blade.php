<x-app-layout title="النسخ الاحتياطي">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">النسخ الاحتياطي</h1>

    <x-panel title="نسخة كاملة من قاعدة البيانات" sub="تحميل نسخة كاملة من بيانات النظام كلها كملف واحد، تقدر تحتفظ بيه كنسخة احتياطية.">

        <a
            href="{{ route('backup.database') }}"
            class="inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
        >
            تحميل نسخة قاعدة البيانات
        </a>
    </x-panel>

    <x-panel title="نسخة CSV شاملة" sub="ملف مضغوط فيه ملف CSV منفصل لكل جدول من جداول النظام، بيتفتح في Excel." class="mt-4">

        <a
            href="{{ route('backup.csv') }}"
            class="inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
        >
            تحميل نسخة CSV شاملة
        </a>
    </x-panel>

    <x-panel title="تصدير ملفات منفصلة (Excel)" sub="الملفات دي مقروءة ومخصصة للفتح على Excel. للنسخة الاحتياطية الكاملة استخدم الزرارين فوق." class="mt-4">

        @php
            // Rooms/expenses/payments/withdrawals carry a season (payments via
            // their room — specs/012 ق-5), so they alone get the season filter.
            $seasonAware = ['exports.rooms', 'exports.payments', 'exports.expenses', 'exports.withdrawals', 'exports.partners'];
        @endphp

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'exports.customers' => ['العملاء', 'الاسم والتليفون والمدفوع والمتبقي لكل عميل'],
                'exports.rooms' => ['الغرف', 'كل غرفة بالتكلفة والربح والتحصيل'],
                'exports.materials' => ['الخامات', 'الخامات والكمية المتاحة وسعر الوحدة'],
                'exports.purchases' => ['المشتريات', 'حركات الشراء من المخزن'],
                'exports.payments' => ['المدفوعات', 'دفعات العملاء بالإيصال وطريقة الدفع'],
                'exports.expenses' => ['المصروفات', 'المصروفات الإدارية بالبند وطريقة الدفع'],
                'exports.cashbox' => ['الخزنة', 'كل حركات الخزنة داخل وخارج'],
                'exports.partners' => ['الشركاء', 'النسبة والإيميل والمسحوب'],
                'exports.withdrawals' => ['السحوبات', 'سحوبات الشركاء بالتاريخ والملاحظة'],
            ] as $route => [$title, $description])
                @if (in_array($route, $seasonAware, true))
                    <form method="GET" action="{{ route($route) }}" class="rounded-lg border border-border p-3">
                        <span class="block text-sm font-medium text-ink">{{ $title }}</span>
                        <span class="block text-xs text-secondary">{{ $description }}</span>
                        <div class="mt-2 flex items-center gap-2">
                            <select name="season" class="flex-1 rounded-md border border-border bg-surface px-2 py-1 text-xs text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                                <option value="all">كل المواسم</option>
                                <option value="open">الموسم المفتوح</option>
                                @foreach ($seasons as $season)
                                    <option value="{{ $season->id }}">{{ 'موسم '.$season->number }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-dark">تحميل</button>
                        </div>
                    </form>
                @else
                    <a
                        href="{{ route($route) }}"
                        class="block rounded-lg border border-border p-3 transition-colors hover:bg-bg-subtle focus:outline-none focus:ring-2 focus:ring-primary/40"
                    >
                        <span class="block text-sm font-medium text-ink">{{ $title }}</span>
                        <span class="block text-xs text-secondary">{{ $description }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </x-panel>
</x-app-layout>
