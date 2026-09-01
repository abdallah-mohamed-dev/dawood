<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Models\Room;
use App\Models\RoomCost;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns labour payments and extra expenses booked against a room — see
 * docs/profit-calculation.md. Every row is cash leaving the drawer on the
 * day it's recorded, so creating one always writes a matching cashbox
 * outflow and deleting one always removes it.
 */
class RoomCostService
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function create(Room $room, RoomCostType $type, int $amount, DateTimeInterface|string $date, ?string $description = null, PaymentMethod $method = PaymentMethod::Cash): RoomCost
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Room cost amount must be greater than zero.');
        }

        return DB::transaction(function () use ($room, $type, $amount, $date, $description, $method) {
            // Re-fetched under a lock so this serializes against
            // RoomService::deleteRoom(), which locks the same row before
            // checking for costs — otherwise a cost could be inserted just
            // after that check and survive as an orphan cashbox row.
            $room = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            $cost = RoomCost::query()->create([
                'room_id' => $room->id,
                'type' => $type,
                'description' => $description,
                'amount' => $amount,
                'occurred_at' => $date,
            ]);

            $this->cashbox->recordOut($cost, $amount, $type->cashboxKind(), $date, method: $method);

            return $cost;
        });
    }

    public function delete(RoomCost $cost): void
    {
        DB::transaction(function () use ($cost) {
            $this->cashbox->removeFor($cost);
            $cost->delete();
        });
    }
}
