<?php

namespace App\Exceptions;

use RuntimeException;

class SeasonCloseFailedException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
