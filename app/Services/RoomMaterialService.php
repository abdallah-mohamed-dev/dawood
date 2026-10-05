<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\ExceedsRequiredQuantityException;
use App\Exceptions\RoomLockedException;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns adding material requirements to a room and issuing against them —
 * see docs/customers-and-rooms.md. Issuing delegates the stock movement and the
 * pricing to InventoryService (quantity × the material's current unit price)
 * and only tracks the room-side bookkeeping (issued_quantity / cost, both
 * cumulative across possibly several issues at different prices).
 *
 * A completed room is locked: nothing about its requirements can change.
 */
class RoomMaterialService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function addRequirement(Room $room, Material $material, int $requiredQuantity): RoomMaterial
    {
        if ($requiredQuantity <= 0) {
            throw new InvalidArgumentException('Required quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($room, $material, $requiredQuantity) {
            $this->lockOpenRoom($room->getKey());

            return RoomMaterial::query()->create([
                'room_id' => $room->id,
                'material_id' => $material->id,
                'required_quantity' => $requiredQuantity,
                // Explicit, not left to the DB column default: create() does not
                // refresh the returned instance's in-memory attributes from
                // DB-computed defaults, so getRawOriginal() would see null
                // instead of 0 on the object this method hands back.
                'issued_quantity' => 0,
                'cost' => 0,
            ]);
        });
    }

    public function issue(RoomMaterial $roomMaterial, int $quantity, DateTimeInterface|string $date): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Issue quantity must be greater than zero.');
        }

        DB::transaction(function () use ($roomMaterial, $quantity, $date) {
            $roomMaterial = RoomMaterial::query()->whereKey($roomMaterial->getKey())->lockForUpdate()->firstOrFail();
            $this->lockOpenRoom($roomMaterial->room_id);

            $alreadyIssued = $roomMaterial->getRawOriginal('issued_quantity');
            $required = $roomMaterial->getRawOriginal('required_quantity');

            if ($alreadyIssued + $quantity > $required) {
                throw new ExceedsRequiredQuantityException($roomMaterial, $alreadyIssued + $quantity, $required);
            }

            $result = $this->inventory->issue($roomMaterial->material, $quantity, $roomMaterial, $date);

            $roomMaterial->update([
                'issued_quantity' => $alreadyIssued + $quantity,
                'cost' => $roomMaterial->getRawOriginal('cost') + $result['cost'],
            ]);
        });
    }

    /**
     * Changes how much of a material the room needs. Lowering it below what
     * has already been issued is refused — to go lower, remove the row (which
     * puts everything back in stock) and add it again.
     */
    public function updateRequirement(RoomMaterial $roomMaterial, int $requiredQuantity): void
    {
        if ($requiredQuantity <= 0) {
            throw new InvalidArgumentException('Required quantity must be greater than zero.');
        }

        DB::transaction(function () use ($roomMaterial, $requiredQuantity) {
            $roomMaterial = RoomMaterial::query()->whereKey($roomMaterial->getKey())->lockForUpdate()->firstOrFail();
            $this->lockOpenRoom($roomMaterial->room_id);

            $issued = $roomMaterial->getRawOriginal('issued_quantity');

            if ($requiredQuantity < $issued) {
                throw new ExceedsRequiredQuantityException($roomMaterial, $issued, $requiredQuantity);
            }

            // Only the requirement moves. Issued quantity and cost are history.
            $roomMaterial->update(['required_quantity' => $requiredQuantity]);
        });
    }

    /**
     * Removes a requirement. Anything already issued against it goes back to
     * stock first, in the same transaction, so a failure leaves both sides as
     * they were. No cashbox movement: the money was paid out when the stock
     * was added, and the transfer to the room is internal.
     */
    public function removeRequirement(RoomMaterial $roomMaterial): void
    {
        // Re-fetch under lock rather than trusting the caller's instance —
        // same reasoning as InventoryService::addStock(): a concurrent
        // issue() could have updated issued_quantity through a different
        // model instance since $roomMaterial was loaded.
        DB::transaction(function () use ($roomMaterial) {
            $roomMaterial = RoomMaterial::query()->whereKey($roomMaterial->getKey())->lockForUpdate()->firstOrFail();
            $this->lockOpenRoom($roomMaterial->room_id);

            if ($roomMaterial->hasBeenIssued()) {
                $this->inventory->returnIssued($roomMaterial);
            }

            $roomMaterial->delete();
        });
    }

    /**
     * Locks the room row and refuses if it is completed. Called inside every
     * transaction that changes a room's requirements.
     */
    private function lockOpenRoom(int $roomId): void
    {
        $room = Room::query()->whereKey($roomId)->lockForUpdate()->firstOrFail();

        // A room inside a closed season is locked whatever its status (specs/012 ق-5).
        if ($room->status === RoomStatus::Completed || $room->season_id !== null) {
            throw new RoomLockedException($room);
        }
    }
}
