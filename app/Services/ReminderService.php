<?php

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Carbon\CarbonImmutable;

/**
 * Two reminders: the season is due to be closed, and the database has not been
 * backed up for a while. Both only read — nothing here writes or closes anything
 * (specs/012 ق-3: closing is always the user's own button).
 */
class ReminderService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array{message: string, url: string}|null
     */
    public function seasonDue(): ?array
    {
        $season = Season::query()->where('status', SeasonStatus::Open)->first();

        if ($season === null) {
            return null;
        }

        $months = max(1, $this->settings->getInt('season_length_months'));
        $due = $season->started_at->toImmutable()->addMonths($months);
        $today = CarbonImmutable::today();

        if ($today->lessThan($due)) {
            return null;
        }

        $daysOver = (int) $due->diffInDays($today);

        return [
            'message' => 'الموسم وصل مدته ('.$months.' شهور)'.($daysOver > 0 ? '، عدّى عليه '.$daysOver.' يوم' : '').'. تحب تقفله؟',
            'url' => route('seasons.preview'),
        ];
    }

    /**
     * @return array{message: string, url: string}|null
     */
    public function backupDue(): ?array
    {
        $days = max(1, $this->settings->getInt('backup_reminder_days'));
        $last = $this->settings->get('last_backup_at');
        $today = CarbonImmutable::today();

        if ($last === null || $last === '') {
            return [
                'message' => 'لسه ما اتعملش نسخة احتياطية من الداتا. اعمل واحدة.',
                'url' => route('backup.index'),
            ];
        }

        $lastDate = CarbonImmutable::parse($last);

        if ($today->lessThan($lastDate->addDays($days))) {
            return null;
        }

        return [
            'message' => 'آخر نسخة احتياطية كانت '.$lastDate->format('Y-m-d').'. وقت نسخة جديدة.',
            'url' => route('backup.index'),
        ];
    }

    /**
     * @return list<array{message: string, url: string, kind: string}>
     */
    public function due(): array
    {
        $reminders = [];

        if ($season = $this->seasonDue()) {
            $reminders[] = $season + ['kind' => 'season'];
        }

        if ($backup = $this->backupDue()) {
            $reminders[] = $backup + ['kind' => 'backup'];
        }

        return $reminders;
    }
}
