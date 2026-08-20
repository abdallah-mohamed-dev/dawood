<x-app-layout title="إضافة مادة">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">إضافة مادة</h1>

    <form method="POST" action="{{ route('inventory.materials.store') }}" class="max-w-md space-y-4">
        @csrf

        @include('inventory.materials._fields')

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('inventory.materials.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
