<x-app-layout title="الإعدادات">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">الإعدادات</h1>
    </div>

    <div class="mb-6 rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="mb-1 text-lg font-semibold text-ink">أسماء أنواع الخامات</h2>
        <p class="mb-4 text-xs text-secondary">تغيير الاسم بيظهر في المخزن فورًا.</p>

        <div class="space-y-4">
            @foreach ($materialTypes as $materialType)
                {{--
                    One form per type, all with a field called `name`. Each gets
                    its own error bag, and old() only repopulates the form that
                    actually failed — same rule as the payment-method select.
                --}}
                @php
                    $bag = 'materialType_'.$materialType->id;
                    $isFailedForm = $errors->getBag($bag)->isNotEmpty();
                @endphp

                <form method="POST" action="{{ route('settings.material-types.update', $materialType) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="material_type_{{ $materialType->id }}" class="mb-1 block text-xs font-medium text-ink-soft">الاسم</label>
                        <input
                            id="material_type_{{ $materialType->id }}"
                            type="text"
                            name="name"
                            required
                            value="{{ $isFailedForm ? old('name') : $materialType->name }}"
                            class="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                        >
                        @error('name', $bag)
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                        {{ __('Save') }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-ink">سجلات النظام</h2>
        <a href="{{ route('logs.index') }}" class="inline-block rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink-soft shadow-sm transition-colors hover:bg-bg-subtle">
            سجل العمليات
        </a>
    </div>
    <div class="mb-6 rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="mb-1 text-lg font-semibold text-ink">التذكيرات</h2>
        <p class="mb-4 text-xs text-secondary">التذكيرات بتظهر فوق الصفحات. ومش بتقفل حاجة لوحدها.</p>

        <form method="POST" action="{{ route('settings.reminders.update') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <div>
                <label for="season_length_months" class="mb-1 block text-xs font-medium text-ink-soft">مدة الموسم (بالشهور)</label>
                <input id="season_length_months" type="number" min="1" step="1" name="season_length_months" value="{{ old('season_length_months', $seasonLengthMonths) }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('season_length_months')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="backup_reminder_days" class="mb-1 block text-xs font-medium text-ink-soft">تذكير النسخ الاحتياطي (بالأيام)</label>
                <input id="backup_reminder_days" type="number" min="1" step="1" name="backup_reminder_days" value="{{ old('backup_reminder_days', $backupReminderDays) }}" class="w-32 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30" required>
                @error('backup_reminder_days')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">{{ __('Save') }}</button>
        </form>
    </div>
</x-app-layout>
