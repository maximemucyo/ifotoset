<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The requested time is outside the photographer's open slots (hours, notice, blocked time or another booking).
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The selected time slot is no longer available. Please select another time.')
    {
        parent::__construct($message);
    }
}
