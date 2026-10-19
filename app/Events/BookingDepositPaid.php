<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingDepositPaid
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Booking $booking,
        public Payment $payment
    ) {}
}
