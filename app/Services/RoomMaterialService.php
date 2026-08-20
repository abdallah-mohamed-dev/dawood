<?php

namespace App\Services;

use App\Exceptions\ExceedsRequiredQuantityException;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns adding material requirements to a room and issuing against them —
 * see docs/customers-and-rooms.md. Issuing delegates the actual FIFO
 * allocation to InventoryService and only tracks the room-side bookkeeping
 * (issued_quantity / cost, both cumulative across possibly several issues).
 */
class RoomMaterialService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function addRequirement(Room $room, Material $material, int $requiredQuantity): RoomMaterial
    {
        if ($requiredQuantity <= 0) {
            throw new InvalidArgumentException('Required quantity must be greater than zero.');
        }

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
    }

    public function issue(RoomMaterial $roomMaterial, int $quantity, DateTimeInterface|string $date): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Issue quantity must be greater than zero.');
        }

        DB::transaction(function () use ($roomMaterial, $quantity, $date) {
            $roomMaterial = RoomMaterial::query()->whereKey($roomMaterial->getKey())->lockForUpdate()->firstOrFail();

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

    public function removeRequirement(RoomMaterial $roomMaterial): void
    {
        // Re-fetch under lock rather than trusting the caller's instance —
        // same reasoning as InventoryService::deletePurchase(): a concurrent
        // issue() could have updated issued_quantity through a different
        // model instance since $roomMaterial was loaded.
        DB::transaction(function () use ($roomMaterial) {
            $roomMaterial = RoomMaterial::query()->whereKey($roomMaterial->getKey())->lockForUpdate()->firstOrFail();

            if ($roomMaterial->hasBeenIssued()) {
                throw new InvalidArgumentException('Cannot remove a requirement that has already been issued against.');
            }

            $roomMaterial->delete();
        });
    }
}
