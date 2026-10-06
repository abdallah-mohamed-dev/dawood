@php
    $locked = $room->status === \App\Enums\RoomStatus::Completed;
@endphp

<div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
    <h2 class="mb-1 text-sm font-semibold text-ink">التسعير التقديري</h2>
    <p class="mb-4 text-xs text-secondary">تقدير بيكتبه المستخدم للمقارنة بعدين. ما بيدخلش في الخزنة ولا في الربح.</p>

    @if ($locked)
        <p class="mb-4 rounded-lg bg-bg-subtle px-3 py-2 text-sm text-secondary">الغرفة مكتملة، التسعير مقفول.</p>
    @endif

    <form method="POST" action="{{ route('rooms.pricing.save', $room) }}">
        @csrf
        <fieldset @disabled($locked) class="grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end">
            <div>
                <label for="estimated_materials" class="mb-1 block text-xs font-medium text-ink-soft">الخامات (ج.م)</label>
                <input id="estimated_materials" type="number" step="0.01" min="0" name="estimated_materials" value="{{ old('estimated_materials', $room->estimated_materials) }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('estimated_materials')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="estimated_accessories" class="mb-1 block text-xs font-medium text-ink-soft">الاكسسوارات (ج.م)</label>
                <input id="estimated_accessories" type="number" step="0.01" min="0" name="estimated_accessories" value="{{ old('estimated_accessories', $room->estimated_accessories) }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('estimated_accessories')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="estimated_labor" class="mb-1 block text-xs font-medium text-ink-soft">المصنعية (ج.م)</label>
                <input id="estimated_labor" type="number" step="0.01" min="0" name="estimated_labor" value="{{ old('estimated_labor', $room->estimated_labor) }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('estimated_labor')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="estimated_other" class="mb-1 block text-xs font-medium text-ink-soft">مصروفات أخرى (ج.م)</label>
                <input id="estimated_other" type="number" step="0.01" min="0" name="estimated_other" value="{{ old('estimated_other', $room->estimated_other) }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('estimated_other')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="expected_duration_days" class="mb-1 block text-xs font-medium text-ink-soft">مدة التنفيذ المتوقعة (أيام)</label>
                <input id="expected_duration_days" type="number" step="1" min="1" name="expected_duration_days" value="{{ old('expected_duration_days', $room->expected_duration_days) }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @error('expected_duration_days')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-5">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Save') }}</button>
            </div>
        </fieldset>
    </form>

    @if ($locked)
        <div class="mt-6">
            <h3 class="mb-2 text-sm font-semibold text-ink">التقدير مقابل الفعلي</h3>
            <div class="overflow-x-auto rounded-xl border border-border shadow-sm">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-bg-subtle">
                        <tr>
                            <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">البند</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">التقدير</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الفعلي</th>
                            <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">الفرق</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach (['materials' => 'الخامات', 'accessories' => 'الاكسسوارات', 'labor' => 'المصنعية', 'other' => 'مصروفات أخرى', 'total' => 'الإجمالي'] as $key => $label)
                            @php $line = $pricing[$key]; @endphp
                            <tr @class(['font-bold' => $key === 'total'])>
                                <td class="px-4 py-2">{{ $label }}</td>
                                <td class="px-4 py-2">
                                    @if ($line['estimated'] === null)
                                        —
                                    @else
                                        <x-money :amount="$line['estimated']" />
                                    @endif
                                </td>
                                <td class="px-4 py-2"><x-money :amount="$line['actual']" /></td>
                                <td class="px-4 py-2 text-end">
                                    @if ($line['difference'] === null)
                                        —
                                    @else
                                        <span @class([
                                            'text-danger' => $line['difference'] > 0,
                                            'text-success' => $line['difference'] < 0,
                                        ])><x-money :amount="$line['difference']" /></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-secondary">الفرق الموجب معناه إن التكلفة زادت عن التقدير. الإجمالي بيتقارن بس لما كل البنود يكون ليها تقدير.</p>
        </div>

        @if ($duration['expected'] !== null && $duration['actual'] !== null)
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-border bg-bg-subtle p-4">
                    <div class="text-sm text-secondary">المدة المتوقعة</div>
                    <div class="mt-1 text-xl font-bold text-ink">{{ $duration['expected'] }} يوم</div>
                </div>
                <div class="rounded-xl border border-border bg-bg-subtle p-4">
                    <div class="text-sm text-secondary">المدة الفعلية</div>
                    <div class="mt-1 text-xl font-bold text-ink">{{ $duration['actual'] }} يوم</div>
                </div>
                <div class="rounded-xl border border-border bg-bg-subtle p-4">
                    <div class="text-sm text-secondary">الفرق</div>
                    <div @class([
                        'mt-1 text-xl font-bold',
                        'text-danger' => $duration['difference'] > 0,
                        'text-success' => $duration['difference'] < 0,
                        'text-ink' => $duration['difference'] === 0,
                    ])>{{ $duration['difference'] > 0 ? '+' : '' }}{{ $duration['difference'] }} يوم</div>
                </div>
            </div>
        @endif
    @endif
</div>
