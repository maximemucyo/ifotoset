<?php

namespace App\Mail;

use App\Models\User;

class TeamSeatsSuspendedMail extends NoticeMail
{
    /**
     * @param  list<string>  $memberNames
     */
    public function __construct(public User $owner, public array $memberNames) {}

    protected function subjectLine(): string
    {
        return 'Some team members lost access to your studio';
    }

    protected function notice(): array
    {
        $count = count($this->memberNames);

        return [
            'eyebrow' => 'Team',
            'title' => 'Team access paused',
            'preheader' => 'Your plan has fewer team seats than your team uses.',
            'greeting' => $this->owner->name,
            'paragraphs' => [
                'Your current plan has ' . ($this->owner->seatLimit() ?: 'no') . ' team ' . ($this->owner->seatLimit() === 1 ? 'seat' : 'seats') . ", so access was paused for {$count} team " . ($count === 1 ? 'member' : 'members') . ': ' . implode(', ', $this->memberNames) . '.',
                'Nothing was deleted. Upgrade your plan and their access comes back automatically.',
            ],
            'button' => ['label' => 'View plans', 'url' => route('studio.billing.index')],
        ];
    }
}
