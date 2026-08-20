<x-app-layout title="إضافة غرفة">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">إضافة غرفة</h1>

    <form method="POST" action="{{ route('rooms.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="customer_id" class="mb-1 block text-sm font-medium text-gray-700">العميل</label>
            <select
                id="customer_id"
                name="customer_id"
                required
                class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
                <option value="">اختر عميلًا</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) old('customer_id', request('customer_id')) === $customer->id)>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-field name="room_type" label="نوع الغرفة" :value="old('room_type')" required placeholder="مثال: غرفة نوم" />

        <x-field name="sale_price" label="سعر البيع (ج.م)" type="number" step="0.01" min="0" :value="old('sale_price')" required />

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ url()->previous() }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
