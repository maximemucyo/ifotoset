<?php

namespace App\Mail;

use App\Models\GooglePhotoSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GooglePhotosSyncCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public GooglePhotoSync $sync;

    /**
     * Create a new message instance.
     */
    public function __construct(GooglePhotoSync $sync)
    {
        $this->sync = $sync;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusText = $this->sync->failed_photos > 0 ? "finished with errors" : "completed";
        return new Envelope(
            subject: "Google Photos export for gallery \"" . $this->sync->gallery->title . "\" has " . $statusText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.google_photos_sync_completed',
        );
    }
}
