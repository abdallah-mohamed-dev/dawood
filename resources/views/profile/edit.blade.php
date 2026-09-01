<x-app-layout title="الملف الشخصي">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">الملف الشخصي</h1>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">البيانات الأساسية</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field name="name" label="الاسم" :value="old('name', auth()->user()->name)" required />
                <x-field name="email" label="البريد الإلكتروني" type="email" :value="old('email', auth()->user()->email)" required />

                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                    {{ __('Save') }}
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">تغيير كلمة المرور</h2>

            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field name="current_password" label="كلمة المرور الحالية" type="password" required autocomplete="current-password" />
                <x-field name="password" label="كلمة المرور الجديدة" type="password" required autocomplete="new-password" />
                <x-field name="password_confirmation" label="تأكيد كلمة المرور الجديدة" type="password" required autocomplete="new-password" />

                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                    {{ __('Save') }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
