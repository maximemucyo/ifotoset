<?php

namespace App\Listeners;

use App\Models\PaymentReceipt;
use Illuminate\Mail\Events\MessageSent;

/**
 * Records when a receipt email actually left the mailer (not when it was queued).
 * Receipt mailables expose a public $receiptId, which reaches the event's view data.
 */
class MarkPaymentReceiptSent
{
    public function handle(MessageSent $event): void
    {
        $receiptId = $event->data['receiptId'] ?? null;

        if ($receiptId) {
            PaymentReceipt::whereKey($receiptId)->whereNull('sent_at')->update(['sent_at' => now()]);
        }
    }
}
