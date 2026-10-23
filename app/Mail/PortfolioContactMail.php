<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A visitor's message from the portfolio contact page. Replying goes straight to the visitor.
 */
class PortfolioContactMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param array{name: string, email: string, phone: ?string, message: string} $inquiry
     */
    public function __construct(
        public User $photographer,
        public array $inquiry
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.notifications.address'), 'ifotoset'),
            replyTo: [new Address($this->inquiry['email'], $this->inquiry['name'])],
            subject: "New message from {$this->inquiry['name']} via your portfolio",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portfolio_contact',
            with: [
                'photographer' => $this->photographer,
                'inquiry' => $this->inquiry,
            ],
        );
    }
}
