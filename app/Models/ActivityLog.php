<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per create/update/delete on a tracked model. Rows are written only
 * by ActivityLogService and are never edited — there is no updated_at column.
 */
#[Fillable(['user_id', 'subject_type', 'subject_id', 'subject_label', 'event', 'changes', 'created_at'])]
class ActivityLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
