<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EventAlreadyStartedException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The event has already started.');
    }
}
