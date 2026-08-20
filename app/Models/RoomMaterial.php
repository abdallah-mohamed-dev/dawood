<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use Database\Factories\RoomMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_id', 'material_id', 'required_quantity', 'issued_quantity', 'cost'])]
class RoomMaterial extends Model
{
    /** @use HasFactory<RoomMaterialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_quantity' => QuantityCast::class,
            'issued_quantity' => QuantityCast::class,
            'cost' => MoneyCast::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function hasBeenIssued(): bool
    {
        return $this->getRawOriginal('issued_quantity') > 0;
    }

    public function isFullyIssued(): bool
    {
        return $this->getRawOriginal('issued_quantity') >= $this->getRawOriginal('required_quantity');
    }
}
