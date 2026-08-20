<x-app-layout title="إضافة غرفة">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">إضافة غرفة</h1>

    <form method="POST" action="{{ route('rooms.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="customer_id" class="mb-1 block text-sm font-medium text-gray-700">العميل</label>
            <select
                id="customer_id"
                name="customer_id"
                required
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
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

        <div>
            <label for="room_type" class="mb-1 block text-sm font-medium text-gray-700">نوع الغرفة</label>
            <input
                id="room_type"
                type="text"
                name="room_type"
                value="{{ old('room_type') }}"
                required
                placeholder="مثال: غرفة نوم"
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('room_type')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sale_price" class="mb-1 block text-sm font-medium text-gray-700">سعر البيع (ج.م)</label>
            <input
                id="sale_price"
                type="number"
                step="0.01"
                min="0"
                name="sale_price"
                value="{{ old('sale_price') }}"
                required
                class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
            @error('sale_price')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('Save') }}
            </button>
            <a href="{{ url()->previous() }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
