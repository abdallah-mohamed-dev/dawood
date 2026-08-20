<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Room-level orchestration — the cascading delete described in
 * docs/customers-and-rooms.md: reverse issued materials (if chosen), then
 * remove every payment (each properly unwinding its own cashbox
 * transaction via CustomerPaymentService), then the room itself.
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
                ->with(['roomMaterials', 'customerPayments'])
                ->lockForUpdate()
                ->firstOrFail();

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
