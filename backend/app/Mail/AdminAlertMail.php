<?php

namespace App\Mail;

class AdminAlertMail extends NoticeMail
{
    /**
     * @param array<string, string> $details
     */
    public function __construct(
        public string $headline,
        public string $summary,
        public array $details = [],
        public ?string $actionUrl = null,
        public string $actionLabel = 'Open admin',
    ) {}

    protected function subjectLine(): string
    {
        return '[ifotoset admin] ' . $this->headline;
    }

    protected function notice(): array
    {
        return array_filter([
            'eyebrow' => 'Admin alert',
            'title' => $this->headline,
            'preheader' => $this->summary,
            'paragraphs' => [$this->summary],
            'details' => $this->details,
            'button' => ['label' => $this->actionLabel, 'url' => $this->actionUrl ?? route('admin.dashboard')],
        ]);
    }
}
