<?php

namespace App\Services;

use App\Exceptions\RoomHasCostsException;
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

    public function deleteRoom(Room $room, bool $returnMaterials): void
    {
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
