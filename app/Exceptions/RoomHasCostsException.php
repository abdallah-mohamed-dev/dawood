<?php

namespace App\Exceptions;

use App\Models\Room;
use RuntimeException;

/**
 * Deleting a room that has labour payments or extra expenses would also
 * delete their cashbox rows — money that actually left the drawer would
 * silently reappear in the balance. The user must remove those costs
 * deliberately first.
 */
class RoomHasCostsException extends RuntimeException
{
    public function __construct(public readonly Room $room)
    {
        parent::__construct("Room #{$room->id} still has recorded costs.");
    }
}
