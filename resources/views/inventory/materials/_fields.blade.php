<x-field name="name" label="اسم المادة" :value="old('name', $material->name ?? '')" required />

<x-field name="unit" label="وحدة القياس" :value="old('unit', $material->unit ?? '')" required placeholder="مثال: لوح، متر، قطعة" />
