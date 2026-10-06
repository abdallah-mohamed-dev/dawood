{{-- Shared by the material edit form. $material is null on a page that
     creates one, so every field falls back to old() then to an empty value. --}}
<x-field name="name" label="اسم المادة" :value="old('name', $material->name ?? '')" required />

<div>
    <label for="material_type_id" class="mb-1 block text-sm font-medium text-ink-soft">نوع الخامة</label>
    <select id="material_type_id" name="material_type_id" required class="w-full max-w-md rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
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

{{-- toDecimalString(), not the decimal the cast's get() hands back: the
     unscaled-looking "2.500" is what QuantityCast::toScaledInt() reads back
     without rounding, so a re-save must not quietly shift the number. --}}
<x-field
    name="unit_price"
    label="سعر الوحدة (ج.م)"
    inputmode="decimal"
    :value="old('unit_price', \App\Casts\MoneyCast::toDecimalString((int) ($material->getRawOriginal('unit_price') ?? 0)))"
    required
    placeholder="مثال: 125.50"
/>

<x-field
    name="quantity"
    label="الكمية في المخزن"
    inputmode="decimal"
    :value="old('quantity', \App\Casts\QuantityCast::toDecimalString((int) ($material->getRawOriginal('quantity') ?? 0)))"
    required
    placeholder="مثال: 12.5"
/>

{{-- Required on every save, not only when the quantity moves: the box has no
     memory of the last edit, and a quantity change cannot ask for a payment
     method the user never saw. --}}
<x-payment-method-select name="payment_method" width="w-full max-w-md" />

<p class="rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-ink-soft">
    تنبيه: تغيير الكمية هنا بيتحرك بمبلغ حقيقي — الزيادة بتخصم من الخزنة والنقص بيزوّدها — بالتاريخ النهارده وبطريقة الدفع المختارة. تغيير سعر الوحدة لوحده ما بيحرّكش فلوس، لكنه بيغيّر قيمة المخزن كلها.
</p>
