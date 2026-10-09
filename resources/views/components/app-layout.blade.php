@props(['title' => null])

@php
    // Icons are fixed SVG path strings written by this file — never user input.
    $icons = [
        'users' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.5 3.6-5 7-5s6.2 1.5 7 5"/>',
        'door' => '<rect x="6" y="3" width="12" height="18" rx="1"/><circle cx="14.5" cy="12" r=".6"/>',
        'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
        'cart' => '<path d="M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="19.5" r="1"/><circle cx="17" cy="19.5" r="1"/>',
        'arrows' => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'coin' => '<circle cx="12" cy="12" r="8"/><path d="M14.5 9.5c-.4-.8-1.3-1.2-2.5-1.2-1.4 0-2.5.7-2.5 1.7s1 1.5 2.5 1.8 2.5.8 2.5 1.8-1.1 1.7-2.5 1.7c-1.2 0-2.1-.4-2.5-1.2M12 6.5v1.3M12 16.2v1.3"/>',
        'receipt' => '<path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21z"/><path d="M9 8h6M9 12h6"/>',
        'hand' => '<path d="M4 12h4l3 3h4a1.5 1.5 0 0 0 0-3h-3l-2-2H4zM4 12v6"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 9h18M16 12.5h2"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
        'tool' => '<path d="M14 4l6 6-9 9-6-6zM5 19l2-2"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'partners' => '<circle cx="9" cy="9" r="3"/><circle cx="16.5" cy="10" r="2.5"/><path d="M3.5 19c.6-2.8 2.8-4 5.5-4s4.9 1.2 5.5 4M14 15.2c2.4 0 4.3 1 5 3.3"/>',
        'bank' => '<path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>',
        'backup' => '<path d="M12 4v11m0 0 4-4m-4 4-4-4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>',
    ];

    // «لوحة التحكم» مش في القايمة لحد قرار المستخدم (specs/020.2 — نقطة مفتوحة).
    $navGroups = [
        'الورشة' => [
            ['route' => 'customers.index', 'active' => 'customers.*', 'label' => 'العملاء', 'icon' => 'users'],
            ['route' => 'rooms.index', 'active' => 'rooms.*', 'label' => 'الغرف', 'icon' => 'door'],
        ],
        'المخزن' => [
            ['route' => 'inventory.materials.index', 'active' => 'inventory.materials.*', 'label' => 'المخزن', 'icon' => 'box'],
            ['route' => 'inventory.shortages.index', 'active' => 'inventory.shortages.*', 'label' => 'المشتريات', 'icon' => 'cart'],
            ['route' => 'inventory.movements.index', 'active' => 'inventory.movements.*', 'label' => 'حركات المخزون', 'icon' => 'arrows'],
        ],
        'الفلوس' => [
            ['route' => 'payments.index', 'active' => 'payments.*', 'label' => 'المدفوعات', 'icon' => 'coin'],
            ['route' => 'expenses.index', 'active' => 'expenses.*', 'label' => 'المصروفات الإدارية', 'icon' => 'receipt'],
            ['route' => 'debts.index', 'active' => 'debts.*', 'label' => 'الديون', 'icon' => 'hand'],
            ['route' => 'cashbox.index', 'active' => 'cashbox.*', 'label' => 'الخزنة', 'icon' => 'wallet'],
        ],
        'الإدارة' => [
            ['route' => 'reports.profit', 'active' => 'reports.profit', 'label' => 'تقارير الربح', 'icon' => 'chart'],
            ['route' => 'reports.labor', 'active' => 'reports.labor', 'label' => 'المصنعيات', 'icon' => 'tool'],
            ['route' => 'seasons.index', 'active' => 'seasons.*', 'label' => 'المواسم', 'icon' => 'calendar'],
            ['route' => 'partners.index', 'active' => 'partners.*', 'label' => 'الشركاء', 'icon' => 'partners'],
            ['route' => 'capital.index', 'active' => 'capital.*', 'label' => 'رأس المال', 'icon' => 'bank'],
        ],
    ];

    $footerItems = [
        ['route' => 'backup.index', 'active' => 'backup.*', 'label' => 'النسخ الاحتياطي', 'icon' => 'backup'],
        ['route' => 'settings.index', 'active' => 'settings.*', 'label' => 'الإعدادات', 'icon' => 'settings'],
    ];

    $linkClasses = fn (bool $active) => 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors '
        .($active ? 'bg-nav-active text-ink shadow-sm ring-1 ring-border-soft' : 'text-nav-text hover:bg-white/60 hover:text-ink');
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? "{$title} - ".config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ Vite::fonts('ibm-plex-sans-arabic') }}
</head>
<body class="min-h-screen bg-bg font-sans text-ink antialiased">
    @auth
        <input type="checkbox" id="sidebar-toggle" class="peer hidden">
        <label for="sidebar-toggle" class="fixed inset-0 z-30 hidden bg-ink/40 peer-checked:block md:hidden" aria-hidden="true"></label>
    @endauth

    <div class="flex min-h-screen">
        @auth
            {{--
                sticky (not static) from md up: it stays in the flex row so the
                main column sits beside it, but is pinned to the viewport with
                h-screen instead of stretching to the full page height — a long
                page now scrolls on its own, leaving the sidebar still.
                The nav below keeps flex-1 + overflow-y-auto, so on a viewport
                too short for the menu the sidebar scrolls inside itself rather
                than squashing its items.
            --}}
            <aside class="fixed inset-y-0 start-0 z-40 flex w-[228px] translate-x-full flex-col overflow-y-auto border-e border-border bg-nav text-nav-text shadow-xl transition-transform duration-200 peer-checked:translate-x-0 md:sticky md:top-0 md:h-screen md:translate-x-0">
                <div class="flex h-16 shrink-0 items-center gap-3 px-5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand text-sm font-bold text-brand-text shadow-sm">D</span>
                    <a href="{{ route('dashboard') }}" class="text-base font-bold tracking-wide text-ink">DAWOOD</a>
                </div>

                <nav class="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
                    @foreach ($navGroups as $groupLabel => $items)
                        <div>
                            <p class="px-3 pb-1.5 pt-2 text-[10.5px] font-semibold tracking-wide text-nav-muted">{{ $groupLabel }}</p>
                            <div class="space-y-0.5">
                                @foreach ($items as $item)
                                    @php($active = request()->routeIs($item['active']))
                                    <a href="{{ route($item['route']) }}" class="{{ $linkClasses($active) }}" @if ($active) aria-current="page" @endif>
                                        <svg class="size-[15px] shrink-0 {{ $active ? 'text-primary' : 'text-nav-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="shrink-0 space-y-0.5 border-t border-border p-3">
                    @foreach ($footerItems as $item)
                        @php($active = request()->routeIs($item['active']))
                        <a href="{{ route($item['route']) }}" class="{{ $linkClasses($active) }}" @if ($active) aria-current="page" @endif>
                            <svg class="size-[15px] shrink-0 {{ $active ? 'text-primary' : 'text-nav-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach

                    <div class="mt-2 flex items-center gap-2 rounded-lg p-1 transition-colors {{ request()->routeIs('profile.*') ? 'bg-nav-active shadow-sm' : 'hover:bg-white/60' }}">
                        <a
                            href="{{ route('profile.edit') }}"
                            class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md px-1.5 py-1.5"
                            title="{{ auth()->user()->name }}"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">
                                {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="min-w-0 flex-1 text-right">
                                <span class="block truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</span>
                                <span class="block text-xs text-nav-muted">الملف الشخصي</span>
                            </span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                title="{{ __('Logout') }}"
                                aria-label="{{ __('Logout') }}"
                                class="flex size-8 shrink-0 items-center justify-center rounded-md text-nav-muted transition-colors hover:bg-danger/10 hover:text-danger"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        @endauth

        <div class="flex min-w-0 flex-1 flex-col">
            @auth
                <header class="flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface px-4 md:hidden">
                    <label for="sidebar-toggle" class="flex size-9 cursor-pointer items-center justify-center rounded-md text-ink-soft hover:bg-bg-subtle" aria-label="فتح القائمة">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </label>
                    <span class="text-base font-bold text-primary">DAWOOD</span>
                </header>
            @endauth

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
                @auth
                    @isset($context)
                        {{-- شريط السياق: أرقام الموسم والخزنة والربح ونسخة الباكب، بيظهر في كل صفحة. قراءة بس. --}}
                        <div class="mb-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-border bg-border text-sm shadow-sm md:grid-cols-4">
                            <div class="bg-surface px-4 py-3">
                                <p class="text-[11px] font-semibold text-secondary">الموسم</p>
                                <p class="mt-0.5 truncate font-medium text-ink">{{ $context['seasonName'] ?? 'مفيش موسم مفتوح' }}</p>
                            </div>
                            <div class="bg-surface px-4 py-3">
                                <p class="text-[11px] font-semibold text-secondary">رصيد الخزنة</p>
                                <p class="mt-0.5 font-medium text-ink"><x-money :amount="$context['cashboxBalance']" /></p>
                            </div>
                            <div class="bg-surface px-4 py-3">
                                <p class="text-[11px] font-semibold text-secondary">صافي الربح <span class="font-normal">(الموسم المفتوح)</span></p>
                                <p class="mt-0.5 font-medium text-ink"><x-money :amount="$context['netProfit']" /></p>
                            </div>
                            <div class="bg-surface px-4 py-3">
                                <p class="text-[11px] font-semibold text-secondary">آخر نسخة احتياطية</p>
                                <p class="mt-0.5 font-medium text-ink">{{ $context['lastBackupAt'] ? \Illuminate\Support\Carbon::parse($context['lastBackupAt'])->format('Y-m-d') : 'لسه ماتاخدتش' }}</p>
                            </div>
                        </div>
                    @endisset
                @endauth

                @if (! empty($reminders))
                    <div x-data="reminderBanner()" x-show="! dismissed" class="mb-6 space-y-2">
                        @foreach ($reminders as $reminder)
                            <div @class([
                                'flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3 text-sm',
                                'border-warning/30 bg-warning/10 text-warning' => $reminder['kind'] === 'season',
                                'border-primary/30 bg-primary/5 text-ink-soft' => $reminder['kind'] === 'backup',
                            ])>
                                <span>{{ $reminder['message'] }}</span>
                                <a href="{{ $reminder['url'] }}" class="font-medium underline">افتح</a>
                            </div>
                        @endforeach
                        <button type="button" @click="dismiss()" class="text-xs text-secondary hover:underline">تجاهل لليوم</button>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-4 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success shadow-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm font-medium text-danger shadow-sm">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
