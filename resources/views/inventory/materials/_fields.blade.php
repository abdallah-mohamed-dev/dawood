<x-field name="name" label="اسم المادة" :value="old('name', $material->name ?? '')" required />

<div>
    <label for="material_type_id" class="mb-1 block text-sm font-medium text-gray-700">نوع الخامة</label>
    <select id="material_type_id" name="material_type_id" required class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        <option value="">اختر النوع</option>
        @foreach ($materialTypes as $materialType)
            <option value="{{ $materialType->id }}" @selected((string) old('material_type_id', $material->material_type_id ?? '') === (string) $materialType->id)>{{ $materialType->name }}</option>
        @endforeach
    </select>
    @error('material_type_id')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>

<x-field name="unit" label="وحدة القياس" :value="old('unit', $material->unit ?? '')" required placeholder="مثال: لوح، متر، قطعة" />
