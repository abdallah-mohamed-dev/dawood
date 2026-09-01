@php
    $bag = 'roomCost_' . $type->value;
    $costs = $room->roomCosts->where('type', $type);
    $total = $costs->sum(fn ($cost) => $cost->getRawOriginal('amount'));

    // This partial renders twice on the same page, and both copies share the
    // field names `description`/`amount`/`occurred_at`. old() is global to the
    // request, so reading it unguarded would echo whatever the labour form
    // submitted into the extra-expense form as well. The hidden `type` field
    // says which section actually posted; only that one repopulates.
    $wasSubmitted = old('type') === $type->value;
    $oldDescription = $wasSubmitted ? old('description') : null;
    $oldAmount = $wasSubmitted ? old('amount') : null;
    $oldDate = $wasSubmitted ? old('occurred_at', now()->toDateString()) : now()->toDateString();
@endphp

<div class="mb-6 mt-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
        <span class="text-sm text-secondary">الإجمالي: <x-money :amount="$total" /></span>
    </div>

    <form method="POST" action="{{ route('rooms.costs.store', $room) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">

        <div>
            <label for="{{ $bag }}_description" class="mb-1 block text-xs font-medium text-gray-700">{{ $descriptionLabel }}</label>
            <input id="{{ $bag }}_description" type="text" name="description" value="{{ $oldDescription }}" class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            @error('description', $bag)
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $bag }}_amount" class="mb-1 block text-xs font-medium text-gray-700">المبلغ (ج.م)</label>
            <input id="{{ $bag }}_amount" type="number" step="0.01" min="0" name="amount" value="{{ $oldAmount }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
            @error('amount', $bag)
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $bag }}_occurred_at" class="mb-1 block text-xs font-medium text-gray-700">التاريخ</label>
            <input id="{{ $bag }}_occurred_at" type="date" name="occurred_at" value="{{ $oldDate }}" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
            @error('occurred_at', $bag)
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-payment-method-select :bag="$bag" :id="$bag . '_payment_method'" />

        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
    </form>
</div>

<x-data-table :headings="['التاريخ', $descriptionLabel, 'المبلغ', __('Actions')]" :rows="$costs" :empty="$emptyMessage">
    @foreach ($costs as $cost)
        <tr>
            <td class="px-4 py-2">{{ $cost->occurred_at->format('Y-m-d') }}</td>
            <td class="px-4 py-2 text-secondary">{{ $cost->description ?? '—' }}</td>
            <td class="px-4 py-2"><x-money :amount="$cost->amount" /></td>
            <td class="px-4 py-2 text-end">
                <x-delete-button :action="route('rooms.costs.destroy', [$room, $cost])" />
            </td>
        </tr>
    @endforeach
</x-data-table>
