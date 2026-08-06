<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Collection;

class BookingOverlapException extends Exception
{
    protected Collection $conflictingBookings;

    public function __construct(Collection $conflictingBookings, string $message = "This booking overlaps with existing bookings.")
    {
        parent::__construct($message);
        $this->conflictingBookings = $conflictingBookings;
    }

    public function getConflictingBookings(): Collection
    {
        return $this->conflictingBookings;
    }
}
