<?php

namespace App\Mail;

use App\Models\AccountInvitation;
use App\Models\User;

class TeamInvitationMail extends NoticeMail
{
    public function __construct(
        public AccountInvitation $invitation,
        public User $owner,
        public User $inviter,
        public string $acceptUrl,
    ) {}

    protected function subjectLine(): string
    {
        return "{$this->inviter->name} invited you to join {$this->owner->name} on ifotoset";
    }

    protected function notice(): array
    {
        return [
            'eyebrow' => 'Team invitation',
            'title' => "Join {$this->owner->name}",
            'preheader' => "{$this->inviter->name} invited you to work in their studio on ifotoset.",
            'paragraphs' => [
                "{$this->inviter->name} invited you to join the {$this->owner->name} studio on ifotoset as a " . strtolower($this->invitation->role->label()) . '.',
                $this->invitation->role->description(),
            ],
            'button' => ['label' => 'Accept invitation', 'url' => $this->acceptUrl],
            'footnotes' => [
                'This invitation expires on ' . $this->invitation->expires_at->toFormattedDateString() . '. If you weren\'t expecting it, you can ignore this email.',
            ],
        ];
    }
}
