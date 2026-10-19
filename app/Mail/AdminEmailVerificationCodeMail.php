<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminEmailVerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $admin,
        public string $code,
        public string $newEmail
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Admin Email Verification Code: ' . $this->code
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_email_verification_code',
            with: [
                'admin' => $this->admin,
                'code' => $this->code,
                'newEmail' => $this->newEmail,
                'expiresInMinutes' => 15,
            ]
        );
    }
}
