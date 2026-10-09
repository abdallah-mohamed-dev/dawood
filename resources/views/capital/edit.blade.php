<x-app-layout title="تعديل بند رأس مال">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-ink">تعديل بند رأس مال</h1>

    <form method="POST" action="{{ route('capital.update', $item) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <x-field name="name" label="البند" :value="old('name', $item->name)" required autofocus />
        <x-field name="amount" label="السعر (ج.م)" type="number" step="1" min="0" :value="old('amount', $item->amount)" required />
        <x-field name="occurred_at" label="التاريخ" type="date" :value="old('occurred_at', $item->occurred_at->format('Y-m-d'))" required />
        <x-field name="note" label="ملاحظات" :value="old('note', $item->note)" />

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Save') }}</button>
            <a href="{{ route('capital.index') }}" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
