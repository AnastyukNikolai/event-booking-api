<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EventFullException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('No seats are available for this event.');
    }
}
