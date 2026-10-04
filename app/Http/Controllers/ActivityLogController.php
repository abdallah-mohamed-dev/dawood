<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurgeActivityLogRequest;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('logs.index', [
            'logs' => $this->query($filters)->with('user')->latest('created_at')->latest('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'subjectTypes' => ActivityLogService::subjectTypes(),
            'events' => ['created', 'updated', 'deleted'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($this->filters($request))->with('user')->latest('created_at')->latest('id');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel reads the Arabic text correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['التاريخ والوقت', 'المستخدم', 'العملية', 'نوع الكيان', 'الكيان', 'التفاصيل']);

            $query->cursor()->each(function (ActivityLog $log) use ($handle) {
                fputcsv($handle, [
                    $log->created_at->format('Y-m-d H:i'),
                    $log->user?->name ?? '—',
                    ActivityLogService::eventLabel($log->event),
                    ActivityLogService::subjectTypes()[$log->subject_type] ?? class_basename($log->subject_type),
                    $log->subject_label,
                    ActivityLogService::detailsText($log),
                ]);
            });

            fclose($handle);
        }, 'activity-logs-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function purge(PurgeActivityLogRequest $request): RedirectResponse
    {
        $before = Carbon::parse($request->string('before')->toString())->startOfDay();

        $deleted = ActivityLog::query()->where('created_at', '<', $before)->delete();

        return back()->with('success', "تم حذف {$deleted} سجل قبل {$before->format('Y-m-d')}.");
    }

    /**
     * @return array{from: string, to: string, event: string, subject_type: string, q: string}
     */
    private function filters(Request $request): array
    {
        $event = $request->string('event')->toString();
        $subjectType = $request->string('subject_type')->toString();

        return [
            'from' => $request->date('from')?->toDateString() ?? '',
            'to' => $request->date('to')?->toDateString() ?? '',
            'event' => in_array($event, ['created', 'updated', 'deleted'], true) ? $event : '',
            'subject_type' => array_key_exists($subjectType, ActivityLogService::subjectTypes()) ? $subjectType : '',
            'q' => trim($request->string('q')->toString()),
        ];
    }

    /**
     * @param  array{from: string, to: string, event: string, subject_type: string, q: string}  $filters
     * @return Builder<ActivityLog>
     */
    private function query(array $filters): Builder
    {
        return ActivityLog::query()
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay()))
            ->when($filters['event'] !== '', fn (Builder $q) => $q->where('event', $filters['event']))
            ->when($filters['subject_type'] !== '', fn (Builder $q) => $q->where('subject_type', $filters['subject_type']))
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where('subject_label', 'like', '%'.$filters['q'].'%'));
    }
}
