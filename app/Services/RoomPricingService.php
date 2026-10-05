<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\RoomLockedException;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves a room's estimates. They are a plan written down for later comparison
 * with the actual cost — they never touch the cashbox or the profit.
 */
class RoomPricingService
{
    /**
     * @param  array{materials: ?int, accessories: ?int, labor: ?int, other: ?int}  $estimates  piastres, null = not estimated
     */
    public function save(Room $room, array $estimates, ?int $expectedDurationDays): void
    {
        if ($expectedDurationDays !== null && $expectedDurationDays <= 0) {
            throw new InvalidArgumentException('Expected duration must be greater than zero.');
        }

        foreach ($estimates as $amount) {
            if ($amount !== null && $amount < 0) {
                throw new InvalidArgumentException('Estimates cannot be negative.');
            }
        }

        DB::transaction(function () use ($room, $estimates, $expectedDurationDays) {
            $room = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if ($room->status === RoomStatus::Completed || $room->season_id !== null) {
                throw new RoomLockedException($room);
            }

            $room->estimated_materials = $estimates['materials'];
            $room->estimated_accessories = $estimates['accessories'];
            $room->estimated_labor = $estimates['labor'];
            $room->estimated_other = $estimates['other'];
            $room->expected_duration_days = $expectedDurationDays;

            // Set the first time a pricing is recorded; later edits keep it.
            if ($room->priced_at === null) {
                $room->priced_at = now()->toDateString();
            }

            $room->save();
        });
    }
}
