<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Email the client and photographer about confirmed sessions starting in the next 24 hours';

    public function handle(): int
    {
        $sent = 0;

        Booking::query()
            ->with(['user', 'client', 'package'])
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])
            ->chunkById(100, function ($bookings) use (&$sent) {
                foreach ($bookings as $booking) {
                    $claimed = Booking::whereKey($booking->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                    if (!$claimed || !$booking->user?->is_active) {
                        continue;
                    }

                    if ($booking->client?->email) {
                        Mail::to($booking->client->email)->queue(new BookingReminderMail($booking));
                    }
                    if ($booking->user->email && \App\Enums\NotificationPreference::NewBookings->enabledFor($booking->user)) {
                        Mail::to($booking->user->email)->queue(new BookingReminderMail($booking, forPhotographer: true));
                    }
                    $sent++;
                }
            });

        $this->info("Sent reminders for {$sent} bookings.");

        return self::SUCCESS;
    }
}
