<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\PublicUrlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $newStatus
    ) {}

    public function envelope(): Envelope
    {
        $photographer = $this->booking->user;
        $statusLabel = ucfirst($this->newStatus);

        return new Envelope(
            from: new Address(
                config('mail.notifications.address', env('MAIL_NOTIFICATIONS_ADDRESS', 'notifications@ifotoset.com')),
                $photographer->name . ' via ifotoset'
            ),
            replyTo: [
                new Address($photographer->email, $photographer->name)
            ],
            subject: "Booking {$statusLabel}: {$this->booking->title} with {$photographer->name}",
        );
    }

    public function content(): Content
    {
        $photographer = $this->booking->user;
        $portfolioUrl = app(PublicUrlService::class)->photographerUrl($photographer->username);

        return new Content(
            view: 'emails.booking_status_updated',
            with: [
                'booking' => $this->booking,
                'photographer' => $photographer,
                'client' => $this->booking->client,
                'newStatus' => $this->newStatus,
                'portfolioUrl' => $portfolioUrl,
            ],
        );
    }
}
