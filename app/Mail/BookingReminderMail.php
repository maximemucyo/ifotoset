<?php

namespace App\Mail;

use App\Models\Booking;

class BookingReminderMail extends NoticeMail
{
    public function __construct(public Booking $booking, public bool $forPhotographer = false) {}

    protected function fromNotifications(): bool
    {
        return true;
    }

    protected function subjectLine(): string
    {
        return $this->forPhotographer
            ? 'Tomorrow: ' . $this->booking->title
            : 'Reminder: your photo session with ' . $this->booking->user?->name . ' is tomorrow';
    }

    protected function notice(): array
    {
        $booking = $this->booking->loadMissing(['user', 'client', 'package']);
        $tz = $booking->timezone ?: config('app.timezone');
        $when = $booking->starts_at->copy()->setTimezone($tz)->format('l, F j \a\t g:i A') . " ({$tz})";

        $details = array_filter([
            'When' => $when,
            'Package' => $booking->package?->name,
            'Location' => $booking->location,
            $this->forPhotographer ? 'Client' : 'Photographer' => $this->forPhotographer
                ? trim(($booking->client?->name ?? '') . ' ' . ($booking->client?->email ? "({$booking->client->email})" : ''))
                : $booking->user?->name,
        ]);

        return $this->forPhotographer
            ? [
                'eyebrow' => 'Booking reminder',
                'title' => 'Session tomorrow',
                'preheader' => "{$booking->title} is on {$when}.",
                'greeting' => $booking->user?->name,
                'paragraphs' => ['A quick reminder about tomorrow\'s session.'],
                'details' => $details,
                'button' => ['label' => 'View bookings', 'url' => route('studio.bookings.index')],
            ]
            : [
                'eyebrow' => 'Booking reminder',
                'title' => 'Your session is tomorrow',
                'preheader' => "Your session with {$booking->user?->name} is on {$when}.",
                'greeting' => $booking->client?->name,
                'paragraphs' => ['This is a reminder of your upcoming photo session. If you need to change anything, reply to the photographer directly.'],
                'details' => $details,
            ];
    }
}
