<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\RoomHasCostsException;
use App\Exceptions\SeasonClosedException;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Room-level orchestration — the cascading delete described in
 * docs/customers-and-rooms.md: refuse outright if the room carries labour
 * or extra costs, then reverse issued materials (if chosen), then remove
 * every payment (each properly unwinding its own cashbox transaction via
 * CustomerPaymentService), then the room itself.
 */
class RoomService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CustomerPaymentService $payments,
    ) {}

    /**
     * completed_at is only meaningful while the room is completed. Leaving
     * the completed state clears it; completing again stamps the new date.
     */
    public function changeStatus(Room $room, RoomStatus $status): void
    {
        if (app(SeasonService::class)->isLocked($room)) {
            throw new SeasonClosedException;
        }

        $room->status = $status;
        $room->completed_at = $status === RoomStatus::Completed ? now()->toDateString() : null;

        // Stamped the first time the room goes in progress and never again —
        // a room that comes back from completed keeps its original start.
        if ($status === RoomStatus::InProgress && $room->started_at === null) {
            $room->started_at = now()->toDateString();
        }

        $room->save();
    }

    public function deleteRoom(Room $room, bool $returnMaterials): void
    {
        if (app(SeasonService::class)->isLocked($room)) {
            throw new SeasonClosedException;
        }

        DB::transaction(function () use ($room, $returnMaterials) {
            // Locking the room row here serializes against
            // CustomerPaymentService::create()/update(), which also lock it
            // before writing — so the payments/materials read below can't
            // miss a payment that's concurrently being inserted for this room.
            $room = Room::query()->whereKey($room->getKey())
                ->with(['roomMaterials', 'customerPayments', 'roomCosts'])
                ->lockForUpdate()
                ->firstOrFail();

            // Checked before anything is touched: labour payments and extra
            // expenses are cash that already left the drawer, so cascading
            // them away would silently hand that money back to the balance.
            // The user has to remove them deliberately first.
            if ($room->hasCosts()) {
                throw new RoomHasCostsException($room);
            }

            if ($returnMaterials) {
                foreach ($room->roomMaterials as $roomMaterial) {
                    if ($roomMaterial->hasBeenIssued()) {
                        $this->inventory->returnIssued($roomMaterial);
                    }
                }
            }

            foreach ($room->customerPayments as $payment) {
                $this->payments->delete($payment);
            }

            $room->delete();
        });
    }
}
