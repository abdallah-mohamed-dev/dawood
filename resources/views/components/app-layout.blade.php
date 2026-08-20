@props(['title' => null])

@php
    $navItems = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'لوحة التحكم'],
        ['route' => 'customers.index', 'active' => 'customers.*', 'label' => 'العملاء'],
        ['route' => 'inventory.categories.index', 'active' => 'inventory.categories.*', 'label' => 'التصنيفات'],
        ['route' => 'inventory.materials.index', 'active' => 'inventory.materials.*', 'label' => 'المواد'],
        ['route' => 'inventory.purchases.index', 'active' => 'inventory.purchases.*', 'label' => 'المشتريات'],
        ['route' => 'payments.index', 'active' => 'payments.*', 'label' => 'المدفوعات'],
        ['route' => 'expenses.index', 'active' => 'expenses.*', 'label' => 'المصروفات'],
        ['route' => 'cashbox.index', 'active' => 'cashbox.*', 'label' => 'الخزنة'],
        ['route' => 'reports.profit', 'active' => 'reports.profit', 'label' => 'تقرير الربح'],
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? "{$title} - ".config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    @auth
        <input type="checkbox" id="sidebar-toggle" class="peer hidden">
        <label for="sidebar-toggle" class="fixed inset-0 z-30 hidden bg-gray-900/40 peer-checked:block md:hidden" aria-hidden="true"></label>
    @endauth

    <div class="flex min-h-screen">
        @auth
            <aside class="fixed inset-y-0 start-0 z-40 flex w-64 translate-x-full flex-col border-e border-border bg-surface transition-transform duration-200 peer-checked:translate-x-0 md:static md:translate-x-0">
                <div class="flex h-16 shrink-0 items-center gap-3 border-b border-border px-5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">D</span>
                    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-wide text-primary">DAWOOD</a>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs($item['active']) ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-border p-3">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-secondary hover:bg-gray-100 hover:text-danger">
                            {{ __('Logout') }}
                        </button>
                    </form>
                </div>
            </aside>
        @endauth

        <div class="flex min-w-0 flex-1 flex-col">
            @auth
                <header class="flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface px-4 md:hidden">
                    <label for="sidebar-toggle" class="flex size-9 cursor-pointer items-center justify-center rounded-md text-gray-700 hover:bg-gray-100" aria-label="فتح القائمة">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </label>
                    <span class="text-base font-bold text-primary">DAWOOD</span>
                </header>
            @endauth

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
                @if (session('success'))
                    <div class="mb-4 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
