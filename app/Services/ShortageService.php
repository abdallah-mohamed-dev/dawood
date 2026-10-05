<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomMaterial;
use Illuminate\Support\Collection;

/**
 * What still has to be bought for the active rooms. Stock is shared, so the
 * rooms are walked in id order and each one takes what it needs from what is
 * left — a room only shows a shortage the earlier rooms did not already use up.
 *
 * Read-only: nothing here writes, and nothing here touches the cashbox.
 */
class ShortageService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array{room_id?: int, customer_id?: int, material_type_id?: int, q?: string}  $filters
     * @return Collection<int, array{room: Room, lines: Collection<int, array{roomMaterial: RoomMaterial, stock: int, shortage: int, cost: int}>, total: int}>
     */
    public function forActiveRooms(array $filters = []): Collection
    {
        $rooms = Room::query()
            ->whereIn('status', [RoomStatus::Draft, RoomStatus::InProgress])
            ->with(['customer', 'roomMaterials.material.materialType'])
            ->orderBy('id')
            ->get();

        $materialIds = $rooms->flatMap(fn (Room $room) => $room->roomMaterials->pluck('material_id'))->unique()->values()->all();
        $available = $this->inventory->stockByMaterialIds($materialIds)->map(fn ($quantity) => (int) $quantity)->all();

        $result = collect();

        foreach ($rooms as $room) {
            $lines = collect();

            foreach ($room->roomMaterials as $roomMaterial) {
                $stock = $available[$roomMaterial->material_id] ?? 0;
                $shortage = $roomMaterial->shortageQuantity($stock);

                // What this room takes from the shared stock, so the next room sees less.
                $available[$roomMaterial->material_id] = $stock - min($stock, $roomMaterial->outstandingQuantity());

                if ($shortage > 0) {
                    $lines->push([
                        'roomMaterial' => $roomMaterial,
                        'stock' => $stock,
                        'shortage' => $shortage,
                        'cost' => $this->inventory->valueOf($roomMaterial->material, $shortage),
                    ]);
                }
            }

            if ($lines->isNotEmpty()) {
                $result->push(['room' => $room, 'lines' => $lines, 'total' => $lines->sum('cost')]);
            }
        }

        return $this->applyFilters($result, $filters);
    }

    /**
     * @param  Collection<int, array{room: Room, lines: Collection<int, array>, total: int}>  $rooms
     * @return Collection<int, array{room: Room, lines: Collection<int, array>, total: int}>
     */
    private function applyFilters(Collection $rooms, array $filters): Collection
    {
        $roomId = (int) ($filters['room_id'] ?? 0);
        $customerId = (int) ($filters['customer_id'] ?? 0);
        $typeId = (int) ($filters['material_type_id'] ?? 0);
        $search = trim((string) ($filters['q'] ?? ''));

        return $rooms
            ->filter(fn (array $group) => $roomId === 0 || $group['room']->id === $roomId)
            ->filter(fn (array $group) => $customerId === 0 || $group['room']->customer_id === $customerId)
            ->map(function (array $group) use ($typeId, $search) {
                $lines = $group['lines']->filter(fn (array $line) => ($typeId === 0 || $line['roomMaterial']->material->material_type_id === $typeId)
                    && ($search === '' || str_contains(mb_strtolower($line['roomMaterial']->material->name), mb_strtolower($search))));

                return ['room' => $group['room'], 'lines' => $lines, 'total' => $lines->sum('cost')];
            })
            ->filter(fn (array $group) => $group['lines']->isNotEmpty())
            ->values();
    }
}
