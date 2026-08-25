<?php

namespace App\Mail;

use App\Models\GalleryDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GalleryZipReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public GalleryDownload $download;
    public string $downloadUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(GalleryDownload $download, string $downloadUrl)
    {
        $this->download = $download;
        $this->downloadUrl = $downloadUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $gallery = $this->download->gallery;
        $photographer = $gallery->user;
        
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address', 'notifications@ifotoset.com'),
                $photographer->name
            ),
            replyTo: [
                new \Illuminate\Mail\Mailables\Address(
                    $photographer->email,
                    $photographer->name
                )
            ],
            subject: "Your Photos for " . $gallery->title . " are ready for download",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.gallery_zip_ready',
        );
    }
}
