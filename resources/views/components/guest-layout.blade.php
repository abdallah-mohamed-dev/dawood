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
<body class="min-h-screen bg-bg font-sans text-gray-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-8">
        <div class="mb-6 flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-base font-bold text-white">D</span>
            <span class="text-xl font-bold tracking-wide text-primary">{{ config('app.name') }}</span>
        </div>

        <div class="w-full max-w-sm rounded-lg border border-border bg-surface p-6 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
