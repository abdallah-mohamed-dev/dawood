<?php

namespace App\Exceptions;

use App\Models\Room;
use RuntimeException;

class RoomCancelledException extends RuntimeException
{
    public function __construct(public readonly Room $room)
    {
        parent::__construct("Room #{$room->id} is cancelled and cannot receive new payments.");
    }
}
