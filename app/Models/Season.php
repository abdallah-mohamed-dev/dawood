<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SeasonStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A period of the business. Written only by SeasonService (specs/012 ق-9).
 * The snapshot columns are filled once at close and never recomputed (ق-7).
 */
#[Fillable(['number', 'name', 'started_at', 'ended_at', 'status', 'closed_at', 'revenue', 'cost_of_materials', 'room_costs', 'cancelled_room_costs', 'admin_expenses', 'net_profit', 'loss_carried_in', 'loss_carried_out', 'distributable_profit', 'cashbox_balance_at_close', 'stock_value_at_close', 'wip_carried_forward'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'موسم';

    public function activityLabel(): string
    {
        return 'موسم '.$this->number;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => SeasonStatus::class,
            'started_at' => 'date',
            'ended_at' => 'date',
            'closed_at' => 'datetime',
            'revenue' => MoneyCast::class,
            'cost_of_materials' => MoneyCast::class,
            'room_costs' => MoneyCast::class,
            'cancelled_room_costs' => MoneyCast::class,
            'admin_expenses' => MoneyCast::class,
            'net_profit' => MoneyCast::class,
            'loss_carried_in' => MoneyCast::class,
            'loss_carried_out' => MoneyCast::class,
            'distributable_profit' => MoneyCast::class,
            'cashbox_balance_at_close' => MoneyCast::class,
            'stock_value_at_close' => MoneyCast::class,
            'wip_carried_forward' => MoneyCast::class,
        ];
    }

    public function partnerShares(): HasMany
    {
        return $this->hasMany(SeasonPartnerShare::class);
    }

    public function isOpen(): bool
    {
        return $this->status === SeasonStatus::Open;
    }
}
