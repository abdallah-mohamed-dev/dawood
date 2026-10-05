<?php

namespace App\Exceptions;

use LogicException;

/**
 * Not a RuntimeException on purpose: controllers catch RuntimeException to show
 * their own messages, and this one must reach the handler untouched.
 */
class SeasonClosedException extends LogicException
{
    public function __construct()
    {
        parent::__construct('الموسم ده مقفول، والبيانات دي للقراءة بس.');
    }
}
