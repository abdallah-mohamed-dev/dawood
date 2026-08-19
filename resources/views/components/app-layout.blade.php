@props(['title' => null])

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
    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('dashboard') }}" class="text-lg font-bold text-primary">{{ config('app.name') }}</a>

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-secondary hover:text-danger">
                        {{ __('Logout') }}
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
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
</body>
</html>
