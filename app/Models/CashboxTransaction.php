<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use Database\Factories\CashboxTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single cashbox movement. Every row here must originate from a real
 * business event (customer payment, inventory purchase, expense, partner
 * withdrawal) via App\Services\CashboxService — the only exception is the
 * single opening-balance row, which has a null source.
 */
#[Fillable(['type', 'amount', 'source_type', 'source_id', 'kind', 'description', 'occurred_at'])]
class CashboxTransaction extends Model
{
    /** @use HasFactory<CashboxTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashboxTransactionType::class,
            'kind' => CashboxTransactionKind::class,
            'amount' => MoneyCast::class,
            'occurred_at' => 'date',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
