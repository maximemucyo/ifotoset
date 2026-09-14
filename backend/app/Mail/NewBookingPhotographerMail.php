<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewBookingPhotographerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Booking Request: {$this->booking->title} from {$this->booking->client->name}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        
        return new Content(
            view: 'emails.new_booking_photographer',
            with: [
                'booking' => $this->booking,
                'photographer' => $this->booking->user,
                'client' => $this->booking->client,
                'package' => $this->booking->package,
                'dashboardUrl' => "{$frontendUrl}/studio/bookings",
            ],
        );
    }
}
