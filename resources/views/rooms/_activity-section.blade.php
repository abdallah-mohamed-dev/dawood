<x-panel title="آخر التعديلات على الغرفة" :link="route('logs.index', ['q' => $room->room_type])" link-label="عرض السجل الكامل" class="mb-6">

    @forelse ($activityLogs as $log)
        <div class="flex flex-wrap items-baseline gap-2 border-b border-border py-2 text-sm last:border-0">
            <span class="text-xs text-secondary">{{ $log->created_at->format('Y-m-d H:i') }}</span>
            <span class="font-medium text-ink">{{ $log->subject_label }}</span>
            <span class="text-secondary">{{ \App\Services\ActivityLogService::eventLabel($log->event) }}</span>
            @if (\App\Services\ActivityLogService::detailsText($log) !== '')
                <span class="w-full whitespace-pre-line text-xs text-secondary">{{ \App\Services\ActivityLogService::detailsText($log) }}</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-secondary">لا توجد تعديلات مسجّلة على الغرفة دي بعد.</p>
    @endforelse
</x-panel>
