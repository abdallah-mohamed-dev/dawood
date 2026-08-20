<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RoomStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'room_type', 'sale_price', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => MoneyCast::class,
            'status' => RoomStatus::class,
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

    public function materialsCost(): int
    {
        if ($this->relationLoaded('roomMaterials')) {
            return $this->roomMaterials->sum(fn (RoomMaterial $roomMaterial) => $roomMaterial->getRawOriginal('cost'));
        }

        return (int) $this->roomMaterials()->sum('cost');
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
