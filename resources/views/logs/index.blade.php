<x-app-layout title="سجل العمليات">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-ink">سجل العمليات</h1>
        <a href="{{ route('logs.export', request()->query()) }}" class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink-soft shadow-sm transition-colors hover:bg-bg-subtle focus:outline-none focus:ring-2 focus:ring-primary/30">
            تصدير Excel
        </a>
    </div>

    <form method="GET" action="{{ route('logs.index') }}" class="mb-6 border-b border-border pb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="mb-1 block text-xs font-medium text-secondary">الكيان</label>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
                    </svg>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم الكيان" class="w-48 rounded-full border border-transparent bg-bg-subtle py-2 ps-9 pe-3 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>
            </div>

            <div>
                <label for="from" class="mb-1 block text-xs font-medium text-secondary">من تاريخ</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="w-40 rounded-full border border-transparent bg-bg-subtle px-3 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label for="to" class="mb-1 block text-xs font-medium text-secondary">إلى تاريخ</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="w-40 rounded-full border border-transparent bg-bg-subtle px-3 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label for="event" class="mb-1 block text-xs font-medium text-secondary">العملية</label>
                <select id="event" name="event" class="w-36 rounded-full border border-transparent bg-bg-subtle px-3 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option value="">الكل</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected($filters['event'] === $event)>{{ \App\Services\ActivityLogService::eventLabel($event) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="subject_type" class="mb-1 block text-xs font-medium text-secondary">نوع الكيان</label>
                <select id="subject_type" name="subject_type" class="w-40 rounded-full border border-transparent bg-bg-subtle px-3 py-2 text-sm text-ink transition-colors focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option value="">الكل</option>
                    @foreach ($subjectTypes as $class => $label)
                        <option value="{{ $class }}" @selected($filters['subject_type'] === $class)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                {{ __('Search') }}
            </button>

            @if (collect($filters)->contains(fn ($value) => $value !== ''))
                <a href="{{ route('logs.index') }}" class="text-sm text-secondary hover:text-danger hover:underline">
                    إلغاء الفلاتر
                </a>
            @endif
        </div>
    </form>

    <x-data-table :headings="['التاريخ والوقت', 'المستخدم', 'العملية', 'نوع الكيان', 'الكيان', 'التفاصيل']" :rows="$logs">
        @foreach ($logs as $log)
            <tr>
                <td class="whitespace-nowrap px-4 py-2">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-2">{{ $log->user?->name ?? '—' }}</td>
                <td class="px-4 py-2">{{ \App\Services\ActivityLogService::eventLabel($log->event) }}</td>
                <td class="px-4 py-2">{{ \App\Services\ActivityLogService::subjectTypes()[$log->subject_type] ?? class_basename($log->subject_type) }}</td>
                <td class="px-4 py-2">{{ $log->subject_label }}</td>
                <td class="px-4 py-2 text-xs text-secondary">
                    @foreach ($log->changes ?? [] as $field => $change)
                        <div>{{ \App\Services\ActivityLogService::fieldLabel($field) }}: {{ $change['old'] }} ← {{ $change['new'] }}</div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>

    {{-- مخفي عمدًا: مفيش أي زرار أو لينك بيفتحه. --}}
    <div class="hidden" id="logs-purge-box">
        <form
            method="POST"
            action="{{ route('logs.purge') }}"
            onsubmit="return confirm('هيتم حذف كل السجلات اللي قبل التاريخ ده نهائيًا. متأكد؟')"
            class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-danger/30 bg-danger/5 p-4"
        >
            @csrf
            @method('DELETE')
            <div>
                <label for="before" class="mb-1 block text-xs font-medium text-secondary">حذف السجلات قبل</label>
                <input id="before" type="date" name="before" required class="w-40 rounded-full border border-transparent bg-bg-subtle px-3 py-2 text-sm text-ink focus:border-danger focus:outline-none focus:ring-2 focus:ring-danger/30">
            </div>
            <button type="submit" class="rounded-full bg-danger px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-danger/40 focus:ring-offset-2">
                حذف السجلات قبل هذا التاريخ
            </button>
        </form>
    </div>
</x-app-layout>
