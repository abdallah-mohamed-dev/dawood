<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\LogsActivity;
use Database\Factories\PartnerWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partner_id', 'amount', 'occurred_at', 'note'])]
class PartnerWithdrawal extends Model
{
    /** @use HasFactory<PartnerWithdrawalFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'سحب شريك';

    public function activityLabel(): string
    {
        return ($this->partner?->name ?? '—').' — '.MoneyCast::toDisplayString((int) ($this->attributes['amount'] ?? 0));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'occurred_at' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
