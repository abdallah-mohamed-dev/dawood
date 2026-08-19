<div>
    <label for="category_id" class="mb-1 block text-sm font-medium text-gray-700">التصنيف</label>
    <select
        id="category_id"
        name="category_id"
        required
        class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
    >
        <option value="">اختر تصنيفًا</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) old('category_id', $material->category_id ?? null) === $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @error('category_id')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">اسم المادة</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $material->name ?? '') }}"
        required
        class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
    >
    @error('name')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="unit" class="mb-1 block text-sm font-medium text-gray-700">وحدة القياس</label>
    <input
        id="unit"
        type="text"
        name="unit"
        value="{{ old('unit', $material->unit ?? '') }}"
        required
        placeholder="مثال: لوح، متر، قطعة"
        class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
    >
    @error('unit')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
