<?php

namespace App\Listeners;

use App\Events\BookingStatusChanged;
use App\Mail\BookingStatusUpdatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingStatusNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Defer execution until the database transaction has committed.
     */
    public bool $afterCommit = true;

    public function handle(BookingStatusChanged $event): void
    {
        // Guard against duplicate notification if status didn't actually transition
        if ($event->oldStatus === $event->newStatus) {
            return;
        }

        // Only send updates for confirmed or cancelled transitions
        if (!in_array($event->newStatus, ['confirmed', 'cancelled'], true)) {
            return;
        }

        $booking = $event->booking->loadMissing(['user', 'client']);
        $client = $booking->client;

        if (!$client || empty($client->email)) {
            return;
        }

        try {
            Mail::to($client->email)->send(
                new BookingStatusUpdatedMail($booking, $event->newStatus)
            );
        } catch (\Throwable $e) {
            Log::error("Failed to send booking status update to client [{$client->email}]: " . $e->getMessage());
        }
    }
}
