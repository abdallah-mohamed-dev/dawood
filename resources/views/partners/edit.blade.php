<x-app-layout title="تعديل شريك">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تعديل شريك</h1>

    <form method="POST" action="{{ route('partners.update', $partner) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <x-field name="name" label="الاسم" :value="old('name', $partner->name)" required autofocus />

        <div>
            <label for="percentage" class="mb-1 block text-sm font-medium text-gray-700">النسبة المئوية (%)</label>
            <input
                id="percentage"
                type="number"
                step="0.01"
                min="0"
                name="percentage"
                value="{{ old('percentage', number_format($partner->percentage / 100, 2)) }}"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
            <p class="mt-1 text-xs text-secondary">مثال: 20 للنسبة 20%، و 33.33 للنسبة 33.33%.</p>
            @error('percentage')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('partners.show', $partner) }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>