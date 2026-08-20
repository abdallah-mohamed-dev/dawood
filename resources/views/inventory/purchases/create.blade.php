<x-app-layout title="تسجيل عملية شراء">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تسجيل عملية شراء</h1>

    <form method="POST" action="{{ route('inventory.purchases.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="material_id" class="mb-1 block text-sm font-medium text-gray-700">المادة</label>
            <select
                id="material_id"
                name="material_id"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
                <option value="">اختر مادة</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}" @selected((int) old('material_id') === $material->id)>
                        {{ $material->category->name }} — {{ $material->name }} ({{ $material->unit }})
                    </option>
                @endforeach
            </select>
            @error('material_id')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="quantity" class="mb-1 block text-sm font-medium text-gray-700">الكمية</label>
            <input
                id="quantity"
                type="number"
                step="0.001"
                min="0"
                name="quantity"
                value="{{ old('quantity') }}"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
            @error('quantity')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="unit_cost" class="mb-1 block text-sm font-medium text-gray-700">سعر الوحدة (ج.م)</label>
            <input
                id="unit_cost"
                type="number"
                step="0.01"
                min="0"
                name="unit_cost"
                value="{{ old('unit_cost') }}"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
            @error('unit_cost')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="purchase_date" class="mb-1 block text-sm font-medium text-gray-700">تاريخ الشراء</label>
            <input
                id="purchase_date"
                type="date"
                name="purchase_date"
                value="{{ old('purchase_date', now()->toDateString()) }}"
                required
                class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
            @error('purchase_date')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('inventory.purchases.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
