<?php

namespace App\Mail;

use App\Models\Gallery;
use App\Models\User;

class FavoritesSubmittedMail extends NoticeMail
{
    public function __construct(public User $user, public Gallery $gallery, public string $email, public int $count) {}

    protected function fromNotifications(): bool
    {
        return true;
    }

    protected function subjectLine(): string
    {
        return "{$this->email} sent {$this->count} favorites from {$this->gallery->title}";
    }

    protected function notice(): array
    {
        return [
            'title' => 'A client sent their favorites',
            'preheader' => $this->subjectLine(),
            'greeting' => $this->user->name,
            'paragraphs' => ["{$this->email} picked {$this->count} " . str('photo')->plural($this->count) . " in {$this->gallery->title} and sent them to you."],
            'button' => [
                'label' => 'See the favorites',
                'url' => route('studio.galleries.favorites', ['uuid' => $this->gallery->uuid, 'person' => $this->email]),
            ],
            'footnotes' => ['You can turn this email off in Studio → Settings → Notifications.'],
        ];
    }
}
