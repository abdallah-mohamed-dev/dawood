<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'unit'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function roomMaterials(): HasMany
    {
        return $this->hasMany(RoomMaterial::class);
    }
}
