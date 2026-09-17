<?php

namespace App\Mail;

use App\Models\Gallery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GalleryInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Gallery $gallery;
    public string $inviteUrl;
    public string $photographerName;

    /**
     * Create a new message instance.
     */
    public function __construct(Gallery $gallery, string $inviteUrl, string $photographerName)
    {
        $this->gallery = $gallery;
        $this->inviteUrl = $inviteUrl;
        $this->photographerName = $photographerName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromAddress = config('mail.notifications.address', env('MAIL_NOTIFICATIONS_ADDRESS', 'notifications@ifotoset.com'));

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $this->photographerName . ' via ifotoset'),
            replyTo: [
                new \Illuminate\Mail\Mailables\Address($this->gallery->user->email, $this->photographerName)
            ],
            subject: "Invitation to view gallery: " . $this->gallery->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.gallery_invitation',
        );
    }
}
