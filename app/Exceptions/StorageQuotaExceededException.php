<?php

namespace App\Exceptions;

use Exception;

class StorageQuotaExceededException extends Exception
{
    public function __construct(
        public readonly int $requiredBytes,
        public readonly int $availableBytes,
        public readonly ?int $limitBytes,
        public readonly int $usedBytes,
        public readonly int $reservedBytes = 0,
        string $message = "Storage limit exceeded for current plan tier."
    ) {
        parent::__construct($message);
    }
}
