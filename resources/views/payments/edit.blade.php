<x-app-layout title="تعديل دفعة">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">تعديل دفعة</h1>

    <p class="mb-4 text-sm text-secondary">
        العميل: {{ $payment->room->customer->name }} — الغرفة: {{ $payment->room->room_type }}
    </p>

    <form method="POST" action="{{ route('payments.update', $payment) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <x-field name="amount" label="المبلغ (ج.م)" type="number" step="0.01" min="0" :value="old('amount', $payment->amount)" required autofocus />

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <a href="{{ route('rooms.show', $payment->room) }}" class="rounded-md border border-border px-4 py-2 text-sm text-gray-700 hover:bg-bg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-app-layout>
