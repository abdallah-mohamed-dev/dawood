<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\PaymentMethod;
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
#[Fillable(['type', 'amount', 'source_type', 'source_id', 'kind', 'payment_method', 'description', 'occurred_at'])]
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
            'payment_method' => PaymentMethod::class,
            'amount' => MoneyCast::class,
            'occurred_at' => 'date',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * What this movement actually was, not just what category it belongs to:
     * "كهرباء" rather than "مصروف إداري". Read from the live source record
     * instead of a string frozen at write time, so renaming a customer or a
     * material updates the cashbox page too.
     *
     * Callers listing many rows must eager-load `source` with morphWith (see
     * CashboxController) — without it this is an N+1.
     */
    public function detailedLabel(): string
    {
        $source = $this->source;

        // The source row is gone (or was never there, as with the opening
        // balance). Fall back to the generic kind rather than blowing up.
        if ($source === null) {
            return $this->kind->label();
        }

        return match ($source::class) {
            Expense::class => $source->category?->name ?? $this->kind->label(),
            CustomerPayment::class => $source->room?->customer?->name
                ? $source->room->customer->name.' — '.$source->room->room_type
                : $this->kind->label(),
            InventoryBatch::class => $source->material?->name ?? $this->kind->label(),
            PartnerWithdrawal::class => $source->partner?->name ?? $this->kind->label(),
            RoomCost::class => $source->type->label().' — '.($source->room?->room_type ?? ''),
            default => $this->kind->label(),
        };
    }
}
