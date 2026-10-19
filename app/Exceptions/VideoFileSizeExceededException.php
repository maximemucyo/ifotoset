<?php

namespace App\Exceptions;

use Exception;

class VideoFileSizeExceededException extends Exception
{
    public function __construct(
        public readonly int $fileSizeBytes,
        public readonly int $maxAllowedBytes,
        string $message = "Video file size exceeds the maximum allowed upload limit for your plan."
    ) {
        parent::__construct($message);
    }
}
