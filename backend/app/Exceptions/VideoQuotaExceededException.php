<?php

namespace App\Exceptions;

use Exception;

class VideoQuotaExceededException extends Exception
{
    public function __construct(
        public readonly int $requiredSeconds,
        public readonly int $availableSeconds,
        public readonly int $limitSeconds,
        public readonly int $usedSeconds,
        public readonly int $reservedSeconds = 0,
        string $message = "Hosted video limit exceeded for your current plan."
    ) {
        parent::__construct($message);
    }
}
