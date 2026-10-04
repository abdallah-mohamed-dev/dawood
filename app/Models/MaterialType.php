<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lookup table for the kind of a material (خامة / اكسسوار). Only the names
 * can be changed from the settings page — types are not created or deleted
 * from the UI, though the structure allows it later.
 */
#[Fillable(['name', 'position'])]
class MaterialType extends Model
{
    use LogsActivity;

    protected static string $activityTypeLabel = 'نوع الخامة';

    public function activityLabel(): string
    {
        return $this->name;
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
