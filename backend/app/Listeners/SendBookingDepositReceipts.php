<?php

namespace App\Listeners;

use App\Events\BookingDepositPaid;
use App\Mail\BookingDepositPaidClientMail;
use App\Mail\BookingDepositPaidPhotographerMail;
use App\Models\PaymentReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingDepositReceipts implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Defer execution until the payment database transaction has committed.
     */
    public bool $afterCommit = true;

    public function handle(BookingDepositPaid $event): void
    {
        $booking = $event->booking->loadMissing(['user', 'client']);
        $payment = $event->payment;
        $client = $booking->client;
        $photographer = $booking->user;

        // 1. Client Deposit Receipt (Idempotent)
        if ($client && !empty($client->email)) {
            $clientReceipt = null;
            try {
                $clientReceipt = PaymentReceipt::create([
                    'payment_id' => $payment->id,
                    'type' => 'booking_deposit_client',
                    'recipient_email' => $client->email,
                    'queued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                Log::info("Booking deposit client receipt already queued/sent for payment #{$payment->id}. Skipping duplicate.");
            }

            if ($clientReceipt) {
                try {
                    Mail::to($client->email)->send(
                        new BookingDepositPaidClientMail($booking, $payment)
                    );
                    $clientReceipt->update(['sent_at' => now()]);
                } catch (\Throwable $e) {
                    Log::error("Failed sending deposit receipt to client [{$client->email}]: " . $e->getMessage());
                }
            }
        }

        // 2. Photographer Deposit Notification (Idempotent & respects preference)
        if ($photographer && !empty($photographer->email)) {
            $prefs = $photographer->notification_preferences ?? [];
            $photographerPrefers = $prefs['payment_received'] ?? true;

            if ($photographerPrefers) {
                $photographerReceipt = null;
                try {
                    $photographerReceipt = PaymentReceipt::create([
                        'payment_id' => $payment->id,
                        'type' => 'booking_deposit_photographer',
                        'recipient_email' => $photographer->email,
                        'queued_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    Log::info("Booking deposit photographer notification already queued/sent for payment #{$payment->id}. Skipping duplicate.");
                }

                if ($photographerReceipt) {
                    try {
                        Mail::to($photographer->email)->send(
                            new BookingDepositPaidPhotographerMail($booking, $payment)
                        );
                        $photographerReceipt->update(['sent_at' => now()]);
                    } catch (\Throwable $e) {
                        Log::error("Failed sending deposit notification to photographer [{$photographer->email}]: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
