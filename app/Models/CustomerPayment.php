<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CustomerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['room_id', 'amount', 'paid_at', 'receipt_number', 'note'])]
class CustomerPayment extends Model
{
    /** @use HasFactory<CustomerPaymentFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'دفعة عميل';

    public function activityLabel(): string
    {
        return ($this->room?->room_type ?? '—').' — '.MoneyCast::toDisplayString((int) ($this->attributes['amount'] ?? 0));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'paid_at' => 'date',
        ];
    }

    /**
     * Shown as a 5-digit receipt number (00001). Stored as an integer so the
     * sequence stays numeric; the padding exists only in this display string.
     */
    public function formattedReceiptNumber(): string
    {
        return $this->receipt_number === null ? '—' : str_pad((string) $this->receipt_number, 5, '0', STR_PAD_LEFT);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * The single cashbox row this record wrote, used only so the edit form
     * can show which payment method was chosen — the method lives on the
     * cashbox transaction, not here (see docs/cashbox.md).
     */
    public function cashboxTransaction(): MorphOne
    {
        return $this->morphOne(CashboxTransaction::class, 'source');
    }
}
