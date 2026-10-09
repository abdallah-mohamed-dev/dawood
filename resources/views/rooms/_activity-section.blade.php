<x-panel title="آخر التعديلات على الغرفة" :link="route('logs.index', ['q' => $room->room_type])" link-label="عرض السجل الكامل" class="mb-6">

    @forelse ($activityLogs as $log)
        @php
            $details = \App\Services\ActivityLogService::detailsList($log);

            // Same wash-and-text pairing as <x-status-badge>, written out in
            // full so Tailwind finds the class names literally.
            $tone = match ($log->event) {
                'created' => 'bg-success/10 text-success',
                'updated' => 'bg-warning/10 text-warning',
                'deleted' => 'bg-danger/10 text-danger',
                default => 'bg-bg-subtle text-ink-soft',
            };
        @endphp

        <div class="flex items-center gap-2 border-b border-border-soft py-2 text-sm last:border-0">
            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone }}">
                {{ \App\Services\ActivityLogService::eventLabel($log->event) }}
            </span>

            <span class="min-w-0 max-w-[45%] truncate font-medium text-ink">{{ $log->subject_label }}</span>

            @if ($details !== [])
                {{-- One line, whatever the change count: the full text is on hover. --}}
                <span class="min-w-0 flex-1 truncate text-xs text-secondary" title="{{ implode(' · ', $details) }}">{{ implode(' · ', $details) }}</span>
            @endif

            <span class="ms-auto shrink-0 text-xs tabular-nums text-secondary">{{ $log->created_at->format('Y-m-d H:i') }}</span>
        </div>
    @empty
        <p class="text-sm text-secondary">لا توجد تعديلات مسجّلة على الغرفة دي بعد.</p>
    @endforelse
</x-panel>
