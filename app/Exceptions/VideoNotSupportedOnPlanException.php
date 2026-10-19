<?php

namespace App\Exceptions;

use Exception;

class VideoNotSupportedOnPlanException extends Exception
{
    public function __construct(
        string $message = "Video hosting is not available on your current plan. Please upgrade to Basic, Professional, or Business."
    ) {
        parent::__construct($message);
    }
}
