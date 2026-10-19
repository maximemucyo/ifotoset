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
        $fromAddress = config('mail.notifications.address');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, 'ifotoset'),
            subject: "New Booking Request: {$this->booking->title} from {$this->booking->client->name}",
        );
    }

    public function content(): Content
    {
        
        return new Content(
            view: 'emails.new_booking_photographer',
            with: [
                'booking' => $this->booking,
                'photographer' => $this->booking->user,
                'client' => $this->booking->client,
                'package' => $this->booking->package,
                'dashboardUrl' => route('studio.bookings.index'),
            ],
        );
    }
}
