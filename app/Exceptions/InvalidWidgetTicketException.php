<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWidgetTicketException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The widget ticket is invalid.');
    }
}
