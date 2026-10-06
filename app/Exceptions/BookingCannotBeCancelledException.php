<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingCannotBeCancelledException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The booking cannot be cancelled because the event has already started.');
    }
}
