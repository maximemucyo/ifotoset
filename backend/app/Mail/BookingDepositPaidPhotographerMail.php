<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingDepositPaidPhotographerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Deposit Received: {$this->booking->title} from {$this->booking->client->name}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return new Content(
            view: 'emails.booking_deposit_paid_photographer',
            with: [
                'booking' => $this->booking,
                'payment' => $this->payment,
                'photographer' => $this->booking->user,
                'client' => $this->booking->client,
                'dashboardUrl' => "{$frontendUrl}/studio/bookings",
            ],
        );
    }
}
