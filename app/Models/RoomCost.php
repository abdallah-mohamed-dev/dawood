<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RoomCostType;
use Database\Factories\RoomCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost booked against a single room — either labour paid to a carpenter
 * or any other expense specific to that room. Every row is money that left
 * the cashbox the day it was recorded, so rows are only ever created and
 * deleted through App\Services\RoomCostService.
 */
#[Fillable(['room_id', 'type', 'description', 'amount', 'occurred_at'])]
class RoomCost extends Model
{
    /** @use HasFactory<RoomCostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoomCostType::class,
            'amount' => MoneyCast::class,
            'occurred_at' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
