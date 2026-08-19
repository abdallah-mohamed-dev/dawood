<x-app-layout title="إضافة تصنيف">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">إضافة تصنيف</h1>

    <form method="POST" action="{{ route('inventory.categories.store') }}" class="max-w-md space-y-4">
        @csrf

        @include('inventory.categories._fields')

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Save') }}
            </button>
            <a href="{{ route('inventory.categories.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
