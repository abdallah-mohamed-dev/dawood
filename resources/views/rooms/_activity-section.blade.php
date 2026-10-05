<div class="mb-6 rounded-xl border border-border bg-surface p-4 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">آخر التعديلات على الغرفة</h2>
        <a href="{{ route('logs.index', ['q' => $room->room_type]) }}" class="text-sm text-primary hover:underline">عرض السجل الكامل</a>
    </div>

    @forelse ($activityLogs as $log)
        <div class="flex flex-wrap items-baseline gap-2 border-b border-border py-2 text-sm last:border-0">
            <span class="text-xs text-secondary">{{ $log->created_at->format('Y-m-d H:i') }}</span>
            <span class="font-medium text-gray-900">{{ $log->subject_label }}</span>
            <span class="text-secondary">{{ \App\Services\ActivityLogService::eventLabel($log->event) }}</span>
            @if (\App\Services\ActivityLogService::detailsText($log) !== '')
                <span class="w-full whitespace-pre-line text-xs text-secondary">{{ \App\Services\ActivityLogService::detailsText($log) }}</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-secondary">لا توجد تعديلات مسجّلة على الغرفة دي بعد.</p>
    @endforelse
</div>
