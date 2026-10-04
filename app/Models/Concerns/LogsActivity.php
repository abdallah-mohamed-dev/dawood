<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogService;

/**
 * Records every create/update/delete of the model automatically — no call
 * site has to remember it. Models using this trait must declare
 * $activityTypeLabel and may override activityLabel().
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => app(ActivityLogService::class)->record($model, 'created'));
        static::updated(fn ($model) => app(ActivityLogService::class)->record($model, 'updated'));
        static::deleted(fn ($model) => app(ActivityLogService::class)->record($model, 'deleted'));
    }

    public function activityLabel(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }

    public function activityTypeLabel(): string
    {
        return static::$activityTypeLabel ?? class_basename($this);
    }
}
