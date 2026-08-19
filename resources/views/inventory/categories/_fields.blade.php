<div>
    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">اسم التصنيف</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $category->name ?? '') }}"
        required
        autofocus
        class="w-full max-w-md rounded-md border border-border px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
    >
    @error('name')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
