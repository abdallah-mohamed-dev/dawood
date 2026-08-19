<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single purchase of a material at a fixed unit cost. quantity never
 * changes after creation; remaining_quantity is depleted by FIFO issues
 * and restored by returns (see App\Services\InventoryService). A batch is
 * never deleted once anything has been issued from it — see business-rules.md §4.
 */
#[Fillable(['material_id', 'quantity', 'remaining_quantity', 'unit_cost', 'purchase_date'])]
class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => QuantityCast::class,
            'remaining_quantity' => QuantityCast::class,
            'unit_cost' => MoneyCast::class,
            'purchase_date' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }
}
