<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'unit', 'material_type_id'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'خامة';

    public function activityLabel(): string
    {
        return $this->name;
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function roomMaterials(): HasMany
    {
        return $this->hasMany(RoomMaterial::class);
    }
}
