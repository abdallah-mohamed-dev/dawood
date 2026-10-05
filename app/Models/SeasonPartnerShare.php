<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\LogsActivity;
use Database\Factories\SeasonPartnerShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One partner's frozen share of one season. carried_out is the amount that
 * becomes the next season's carried_in (specs/012 §3.4-ب).
 */
#[Fillable(['season_id', 'partner_id', 'percentage', 'share_amount', 'carried_in', 'withdrawn', 'carried_out'])]
class SeasonPartnerShare extends Model
{
    /** @use HasFactory<SeasonPartnerShareFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'نصيب شريك في موسم';

    public function activityLabel(): string
    {
        return ($this->partner?->name ?? '—').' — موسم '.($this->season?->number ?? '—');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
            'share_amount' => MoneyCast::class,
            'carried_in' => MoneyCast::class,
            'withdrawn' => MoneyCast::class,
            'carried_out' => MoneyCast::class,
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
