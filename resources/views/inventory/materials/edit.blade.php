<x-app-layout title="تعديل مادة">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">تعديل مادة</h1>

    <form method="POST" action="{{ route('inventory.materials.update', $material) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        @include('inventory.materials._fields')

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Save') }}
            </button>
            <a href="{{ route('inventory.materials.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
