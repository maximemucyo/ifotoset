<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingDepositPaidClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        $photographer = $this->booking->user;

        return new Envelope(
            from: new Address(
                config('mail.from.address', 'notifications@ifotoset.com'),
                $photographer->name . ' via ifotoset'
            ),
            replyTo: [
                new Address($photographer->email, $photographer->name)
            ],
            subject: "Deposit Receipt: {$this->booking->title} with {$photographer->name}",
        );
    }

    public function content(): Content
    {
        $photographer = $this->booking->user;

        return new Content(
            view: 'emails.booking_deposit_paid_client',
            with: [
                'booking' => $this->booking,
                'payment' => $this->payment,
                'photographer' => $photographer,
                'client' => $this->booking->client,
            ],
        );
    }
}
