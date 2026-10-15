<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment the provider reported as failed, or one whose paid amount did not match.
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public bool $amountMismatch = false
    ) {}
}
