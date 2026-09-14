<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingConfirmationClientMail;
use App\Mail\NewBookingPhotographerMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Defer execution until the creating database transaction has committed.
     */
    public bool $afterCommit = true;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing(['user', 'client', 'package']);
        $photographer = $booking->user;
        $client = $booking->client;

        if (!$photographer || !$client) {
            return;
        }

        // 1. Photographer Notification: Respects notification preference
        $prefs = $photographer->notification_preferences ?? [];
        $photographerPrefers = $prefs['new_bookings'] ?? true;

        if ($photographerPrefers && !empty($photographer->email)) {
            try {
                Mail::to($photographer->email)->send(
                    new NewBookingPhotographerMail($booking)
                );
            } catch (\Throwable $e) {
                Log::error("Failed to send booking notification to photographer [{$photographer->email}]: " . $e->getMessage());
            }
        }

        // 2. Client Confirmation: Transactional email, always delivered
        if (!empty($client->email)) {
            try {
                Mail::to($client->email)->send(
                    new BookingConfirmationClientMail($booking)
                );
            } catch (\Throwable $e) {
                Log::error("Failed to send booking confirmation to client [{$client->email}]: " . $e->getMessage());
            }
        }
    }
}
