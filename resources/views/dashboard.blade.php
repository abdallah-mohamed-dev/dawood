@php
    $shortcuts = [
        ['route' => 'customers.index', 'label' => 'العملاء', 'hint' => 'إدارة العملاء والغرف'],
        ['route' => 'inventory.purchases.index', 'label' => 'المشتريات', 'hint' => 'تسجيل شراء الخامات'],
        ['route' => 'payments.index', 'label' => 'المدفوعات', 'hint' => 'دفعات العملاء'],
        ['route' => 'expenses.index', 'label' => 'المصروفات', 'hint' => 'المصروفات الإدارية'],
        ['route' => 'cashbox.index', 'label' => 'الخزنة', 'hint' => 'الرصيد والحركات'],
        ['route' => 'reports.profit', 'label' => 'تقرير الربح', 'hint' => 'صافي الربح والتكاليف'],
    ];
@endphp

<x-app-layout title="لوحة التحكم">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900">أهلاً، {{ auth()->user()->name }}</h1>
    <p class="mt-2 text-sm text-secondary">مرحبًا بك في نظام إدارة وحسابات الورشة.</p>

    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($shortcuts as $shortcut)
            <a
                href="{{ route($shortcut['route']) }}"
                class="group rounded-xl border border-border bg-surface p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <span class="text-base font-bold text-gray-900 group-hover:text-primary">{{ $shortcut['label'] }}</span>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5m6 10l-5-5 5-5" />
                        </svg>
                    </span>
                </div>
                <p class="mt-2 text-sm text-secondary">{{ $shortcut['hint'] }}</p>
            </a>
        @endforeach
    </div>
</x-app-layout>
