<x-guest-layout title="تسجيل الدخول">
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-ink">أهلاً بيك تاني</h1>
        <p class="mt-1.5 text-sm text-secondary">سجّل دخولك عشان تكمّل شغل الورشة.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-ink-soft">البريد الإلكتروني</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="you@example.com"
                dir="ltr"
                class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-ink shadow-sm transition-all placeholder:text-secondary/50 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/25"
            >
            @error('email')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-ink-soft">كلمة المرور</label>
            <div class="relative" x-data="{ show: false }">
                <input
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    x-bind:type="show ? 'text' : 'password'"
                    class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 pe-11 text-sm text-ink shadow-sm transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/25"
                >
                <button
                    type="button"
                    @click="show = ! show"
                    class="absolute inset-y-0 end-0 flex items-center px-3 text-secondary transition-colors hover:text-ink"
                    x-bind:aria-label="show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path x-show="! show" stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                        <circle x-show="! show" cx="12" cy="12" r="3"/>
                        <path x-show="show" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.7a3 3 0 0 0 4.2 4.2M9.4 5.9A9.5 9.5 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3.2 3.9M6.2 7.8A16 16 0 0 0 2.5 12S6 18.5 12 18.5c1 0 1.9-.2 2.7-.5"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary">
            تذكرني
        </label>

        <button
            type="submit"
            class="w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
        >
            {{ __('Login') }}
        </button>
    </form>
</x-guest-layout>
