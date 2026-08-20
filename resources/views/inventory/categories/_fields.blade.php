<div>
    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">اسم التصنيف</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $category->name ?? '') }}"
        required
        autofocus
        class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
    >
    @error('name')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
