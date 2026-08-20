<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Enums\RoomStatus;
use App\Exceptions\PaymentExceedsRemainingException;
use App\Exceptions\RoomCancelledException;
use App\Models\CustomerPayment;
use App\Models\Room;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns creating, updating, and deleting customer payments — see
 * docs/customer-payments.md. paid_amount is never stored on Room; it's
 * always derived from these rows, so there is nothing to keep in sync here
 * beyond the linked CashboxService transaction.
 */
class CustomerPaymentService
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function create(Room $room, int $amount, DateTimeInterface|string $date, ?string $note = null): CustomerPayment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($room, $amount, $date, $note) {
            $room = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if ($room->status === RoomStatus::Cancelled) {
                throw new RoomCancelledException($room);
            }

            $this->assertWithinRemaining($room, $amount);

            $payment = CustomerPayment::query()->create([
                'room_id' => $room->id,
                'amount' => $amount,
                'paid_at' => $date,
                'note' => $note,
            ]);

            $this->cashbox->recordIn($payment, $amount, CashboxTransactionKind::CustomerPayment, $date);

            return $payment;
        });
    }

    public function update(CustomerPayment $payment, int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        DB::transaction(function () use ($payment, $amount) {
            $payment = CustomerPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $room = Room::query()->whereKey($payment->room_id)->lockForUpdate()->firstOrFail();

            $this->assertWithinRemaining($room, $amount, excludingPayment: $payment);

            $payment->update(['amount' => $amount]);
            $this->cashbox->updateFor($payment, $amount);
        });
    }

    public function delete(CustomerPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->cashbox->removeFor($payment);
            $payment->delete();
        });
    }

    private function assertWithinRemaining(Room $room, int $amount, ?CustomerPayment $excludingPayment = null): void
    {
        $otherPaid = (int) $room->customerPayments()
            ->when($excludingPayment, fn ($query) => $query->where('id', '!=', $excludingPayment->id))
            ->sum('amount');

        $salePrice = $room->getRawOriginal('sale_price');
        $remaining = $salePrice - $otherPaid;

        if ($amount > $remaining) {
            throw new PaymentExceedsRemainingException($room, $amount, $remaining);
        }
    }
}
