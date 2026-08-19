<x-guest-layout title="تسجيل الدخول">
    <h1 class="mb-6 text-center text-lg font-semibold text-gray-900">تسجيل الدخول</h1>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">البريد الإلكتروني</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="w-full rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('email')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">كلمة المرور</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('password')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary">
            تذكرني
        </label>

        <button
            type="submit"
            class="w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
        >
            {{ __('Login') }}
        </button>
    </form>
</x-guest-layout>
