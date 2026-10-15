<?php

namespace App\Mail;

use App\Models\User;

class PasswordChangedMail extends NoticeMail
{
    public function __construct(public User $user, public ?string $ipAddress = null) {}

    protected function subjectLine(): string
    {
        return 'Your ifotoset password was changed';
    }

    protected function notice(): array
    {
        return [
            'eyebrow' => 'Security',
            'title' => 'Password changed',
            'preheader' => 'The password for your ifotoset account was just changed.',
            'greeting' => $this->user->name,
            'paragraphs' => [
                'The password for your ifotoset account (' . $this->user->email . ') was just changed.',
            ],
            'details' => array_filter([
                'When' => now()->format('F j, Y g:i A') . ' UTC',
                'IP address' => $this->ipAddress,
            ]),
            'callout' => [
                'variant' => 'warning',
                'text' => 'If this wasn\'t you, reset your password now and contact support@ifotoset.com.',
            ],
            'button' => ['label' => 'Reset password', 'url' => route('password.request')],
        ];
    }
}
