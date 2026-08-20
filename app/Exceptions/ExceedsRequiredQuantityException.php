<?php

namespace App\Exceptions;

use App\Models\RoomMaterial;
use RuntimeException;

class ExceedsRequiredQuantityException extends RuntimeException
{
    public function __construct(public readonly RoomMaterial $roomMaterial, public readonly int $attempted, public readonly int $required)
    {
        parent::__construct("Issuing {$attempted} would exceed the required {$required} for room material #{$roomMaterial->id}.");
    }
}
