<?php

namespace App\Mail;

use App\Models\User;
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
            ? 'Action Required: Your ifotoset storage is 100% full'
            : "Notice: You've reached 75% of your ifotoset storage quota";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

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
                'upgradeUrl' => "{$frontendUrl}/studio/billing",
                'galleriesUrl' => "{$frontendUrl}/studio/galleries",
                'trashUrl' => "{$frontendUrl}/studio/trash",
            ]
        );
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $val = round($bytes / pow(1024, $i), 1);
        return "{$val} {$units[$i]}";
    }
}
