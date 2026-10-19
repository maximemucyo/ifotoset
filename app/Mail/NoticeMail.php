<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for short transactional notices rendered by emails/notice.blade.php.
 * Subclasses return the subject and the notice content.
 */
abstract class NoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    abstract protected function subjectLine(): string;

    /**
     * @return array{title: string, eyebrow?: string, subtitle?: string, preheader?: string, greeting?: string,
     *     paragraphs?: list<string>, callout?: array{variant: string, text: string}, detailsTitle?: string,
     *     details?: array<string, string>, button?: array{label: string, url: string}, footnotes?: list<string>}
     */
    abstract protected function notice(): array;

    /**
     * Account and billing notices come from the default address; activity
     * notices may override this to use the notifications sender.
     */
    protected function fromNotifications(): bool
    {
        return false;
    }

    public function envelope(): Envelope
    {
        $from = $this->fromNotifications()
            ? new Address(config('mail.notifications.address'), config('mail.notifications.name'))
            : null;

        return new Envelope(from: $from, subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice', with: ['notice' => $this->notice()]);
    }
}
