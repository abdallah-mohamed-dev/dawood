<?php

namespace App\Exceptions;

use App\Models\Material;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly Material $material, public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Cannot issue {$requested} of material #{$material->id}; only {$available} available.");
    }
}
