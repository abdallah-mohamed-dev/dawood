<?php

namespace App\Exceptions;

use App\Models\Room;
use RuntimeException;

class PaymentExceedsRemainingException extends RuntimeException
{
    public function __construct(public readonly Room $room, public readonly int $attempted, public readonly int $remaining)
    {
        parent::__construct("Payment of {$attempted} exceeds the remaining {$remaining} for room #{$room->id}.");
    }
}
