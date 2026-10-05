<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'room_type', 'sale_price', 'status', 'completed_at', 'estimated_materials', 'estimated_accessories', 'estimated_labor', 'estimated_other', 'priced_at', 'expected_duration_days', 'started_at'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'غرفة';

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function activityLabel(): string
    {
        return $this->room_type.' — '.($this->customer?->name ?? '—');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => MoneyCast::class,
            'status' => RoomStatus::class,
            'completed_at' => 'date',
            'started_at' => 'date',
            'priced_at' => 'date',
            'expected_duration_days' => 'integer',
            'estimated_materials' => MoneyCast::class,
            'estimated_accessories' => MoneyCast::class,
            'estimated_labor' => MoneyCast::class,
            'estimated_other' => MoneyCast::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function roomMaterials(): HasMany
    {
        return $this->hasMany(RoomMaterial::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function roomCosts(): HasMany
    {
        return $this->hasMany(RoomCost::class);
    }

    /**
     * Cost of this room's materials grouped by material type — [type_id => cost].
     * Reads the loaded relation; loads it once if it is not loaded yet.
     *
     * @return array<int, int>
     */
    public function materialsCostByType(): array
    {
        $roomMaterials = $this->relationLoaded('roomMaterials')
            ? $this->roomMaterials
            : $this->roomMaterials()->with('material')->get();

        return $roomMaterials
            ->groupBy(fn (RoomMaterial $roomMaterial) => $roomMaterial->material->material_type_id)
            ->map(fn ($group) => (int) $group->sum(fn (RoomMaterial $roomMaterial) => $roomMaterial->getRawOriginal('cost')))
            ->all();
    }

    public function materialsCost(): int
    {
        if ($this->relationLoaded('roomMaterials')) {
            return $this->roomMaterials->sum(fn (RoomMaterial $roomMaterial) => $roomMaterial->getRawOriginal('cost'));
        }

        return (int) $this->roomMaterials()->sum('cost');
    }

    public function laborCost(): int
    {
        return $this->costOfType(RoomCostType::Labor);
    }

    public function otherCost(): int
    {
        return $this->costOfType(RoomCostType::Other);
    }

    public function costsTotal(): int
    {
        if ($this->relationLoaded('roomCosts')) {
            return $this->roomCosts->sum(fn (RoomCost $cost) => $cost->getRawOriginal('amount'));
        }

        return (int) $this->roomCosts()->sum('amount');
    }

    public function hasCosts(): bool
    {
        if ($this->relationLoaded('roomCosts')) {
            return $this->roomCosts->isNotEmpty();
        }

        return $this->roomCosts()->exists();
    }

    private function costOfType(RoomCostType $type): int
    {
        if ($this->relationLoaded('roomCosts')) {
            return $this->roomCosts
                ->where('type', $type)
                ->sum(fn (RoomCost $cost) => $cost->getRawOriginal('amount'));
        }

        return (int) $this->roomCosts()->where('type', $type)->sum('amount');
    }

    public function paidAmount(): int
    {
        if ($this->relationLoaded('customerPayments')) {
            return $this->customerPayments->sum(fn (CustomerPayment $payment) => $payment->getRawOriginal('amount'));
        }

        return (int) $this->customerPayments()->sum('amount');
    }

    public function remainingAmount(): int
    {
        return $this->getRawOriginal('sale_price') - $this->paidAmount();
    }

    public function hasIssuedMaterials(): bool
    {
        if ($this->relationLoaded('roomMaterials')) {
            return $this->roomMaterials->contains(fn (RoomMaterial $roomMaterial) => $roomMaterial->hasBeenIssued());
        }

        return $this->roomMaterials()->where('issued_quantity', '>', 0)->exists();
    }
}
