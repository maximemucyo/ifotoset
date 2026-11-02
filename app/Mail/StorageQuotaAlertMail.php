<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\User;
use App\Support\Bytes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StorageQuotaAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $threshold, // 75 or 100
        public int $usedBytes,
        public int $limitBytes
    ) {}

    public function envelope(): Envelope
    {
        $subject = ($this->threshold >= 100)
            ? 'Your ifotoset storage is full'
            : "You've used 75% of your ifotoset storage";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {

        return new Content(
            view: 'emails.storage_quota_alert',
            with: [
                'user' => $this->user,
                'threshold' => $this->threshold,
                'usedBytes' => $this->usedBytes,
                'limitBytes' => $this->limitBytes,
                'percentUsed' => $this->limitBytes > 0 ? min(100, round(($this->usedBytes / $this->limitBytes) * 100)) : 100,
                'usedFormatted' => $this->formatBytes($this->usedBytes),
                'limitFormatted' => $this->formatBytes($this->limitBytes),
                'upgradeUrl' => route('studio.billing.index'),
                'galleriesUrl' => route('studio.galleries.index'),
                'trashUrl' => route('studio.trash.index'),
                // The smallest plan with more room, so the email never names a stale plan or size
                'nextPlan' => Plan::where('storage_limit', '>', $this->limitBytes)->orderBy('storage_limit')->first(),
            ]
        );
    }

    protected function formatBytes(int $bytes): string
    {
        return Bytes::format($bytes);
    }
}
