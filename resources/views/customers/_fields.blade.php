<div>
    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">اسم العميل</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $customer->name ?? '') }}"
        required
        autofocus
        class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
    @error('name')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">رقم الهاتف</label>
    <input
        id="phone"
        type="text"
        name="phone"
        value="{{ old('phone', $customer->phone ?? '') }}"
        class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
    @error('phone')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="address" class="mb-1 block text-sm font-medium text-gray-700">العنوان</label>
    <textarea
        id="address"
        name="address"
        rows="2"
        class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >{{ old('address', $customer->address ?? '') }}</textarea>
    @error('address')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
