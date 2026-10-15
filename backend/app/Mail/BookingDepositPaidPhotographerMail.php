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

    /**
     * PaymentReceipt row this email fulfils; MarkPaymentReceiptSent stamps it on delivery.
     */
    public ?int $receiptId = null;

    public function forReceipt(int $receiptId): static
    {
        $this->receiptId = $receiptId;

        return $this;
    }

    public function __construct(
        public Booking $booking,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.notifications.address');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, 'ifotoset'),
            subject: "Deposit Received: {$this->booking->title} from {$this->booking->client->name}",
        );
    }

    public function content(): Content
    {

        return new Content(
            view: 'emails.booking_deposit_paid_photographer',
            with: [
                'booking' => $this->booking,
                'payment' => $this->payment,
                'photographer' => $this->booking->user,
                'client' => $this->booking->client,
                'dashboardUrl' => route('studio.bookings.index'),
            ],
        );
    }
}
