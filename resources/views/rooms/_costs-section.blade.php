@php
    use App\Enums\RoomCostType;

    $costs = $room->roomCosts;
    $laborTotal = $costs->where('type', RoomCostType::Labor)->sum(fn ($cost) => $cost->getRawOriginal('amount'));
    $otherTotal = $costs->where('type', RoomCostType::Other)->sum(fn ($cost) => $cost->getRawOriginal('amount'));

    $currentType = RoomCostType::tryFrom((string) old('type')) ?? RoomCostType::Labor;
    $bag = 'roomCost_'.$currentType->value;
    $wasSubmitted = $errors->getBag($bag)->isNotEmpty();
    $oldDescription = $wasSubmitted ? old('description') : null;
    $oldAmount = $wasSubmitted ? old('amount') : null;
    $oldDate = $wasSubmitted ? old('occurred_at', now()->toDateString()) : now()->toDateString();
@endphp

<x-panel
    id="costs-section"
    x-data="{ formOpen: {{ $wasSubmitted ? 'true' : 'false' }} }"
    x-on:open-cost-form.window="formOpen = true"
>
    <x-slot:title>
        تكاليف الغرفة
        <span class="text-xs font-normal text-secondary">(مصنعية <x-money :amount="$laborTotal" /> · مصروفات <x-money :amount="$otherTotal" />)</span>
    </x-slot:title>

    <x-slot:actions>
        <button type="button" @click="formOpen = ! formOpen" class="rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink-soft transition-colors hover:bg-bg-subtle hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/40">+ تكلفة</button>
    </x-slot:actions>

    <div x-show="formOpen" x-cloak class="mb-4 rounded-lg border border-border-soft bg-bg-subtle p-3">
        <form method="POST" action="{{ route('rooms.costs.store', $room) }}" class="grid grid-cols-2 gap-3">
            @csrf

            <div>
                <label for="cost_type" class="mb-1 block text-xs font-medium text-ink-soft">النوع</label>
                <select id="cost_type" name="type" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @foreach (RoomCostType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($currentType === $type)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cost_amount" class="mb-1 block text-xs font-medium text-ink-soft">المبلغ (ج.م)</label>
                <input id="cost_amount" type="number" step="1" min="0" name="amount" value="{{ $oldAmount }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('amount', $bag)
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="col-span-2">
                <label for="cost_description" class="mb-1 block text-xs font-medium text-ink-soft">الوصف</label>
                <input id="cost_description" type="text" name="description" value="{{ $oldDescription }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('description', $bag)
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="cost_occurred_at" class="mb-1 block text-xs font-medium text-ink-soft">التاريخ</label>
                <input id="cost_occurred_at" type="date" name="occurred_at" value="{{ $oldDate }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('occurred_at', $bag)
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <x-payment-method-select :bag="$bag" id="cost_payment_method" width="w-full" />

            <div class="col-span-2 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Add') }}</button>
                <button type="button" @click="formOpen = false" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>

    <div class="min-w-0 overflow-x-auto">
        <x-data-table :headings="['التاريخ', 'النوع', 'الوصف', 'المبلغ', __('Actions')]" :rows="$costs" empty="لا توجد تكاليف مسجّلة لهذه الغرفة.">
            @foreach ($costs->sortByDesc('occurred_at') as $cost)
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">{{ $cost->occurred_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-flex items-center rounded-md bg-bg-subtle px-2 py-0.5 text-[11px] font-semibold text-secondary">{{ $cost->type->label() }}</span>
                    </td>
                    <td class="px-4 py-2 text-secondary">{{ $cost->description ?? '—' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap"><x-money :amount="$cost->amount" /></td>
                    <td class="px-4 py-2 text-end">
                        <x-delete-button :action="route('rooms.costs.destroy', [$room, $cost])" />
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-panel>
