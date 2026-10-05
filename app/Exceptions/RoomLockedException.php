<?php

namespace App\Exceptions;

use App\Models\Room;
use RuntimeException;

class RoomLockedException extends RuntimeException
{
    public function __construct(public readonly Room $room)
    {
        parent::__construct("Room #{$room->id} is completed and locked.");
    }
}
