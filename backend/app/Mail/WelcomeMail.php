<?php

namespace App\Mail;

use App\Models\User;

class WelcomeMail extends NoticeMail
{
    public function __construct(public User $user) {}

    protected function subjectLine(): string
    {
        return 'Welcome to ifotoset';
    }

    protected function notice(): array
    {
        return [
            'eyebrow' => 'Welcome',
            'title' => 'Your studio is ready',
            'preheader' => 'Create your first gallery and share it with a client in a few minutes.',
            'greeting' => $this->user->name,
            'paragraphs' => [
                'Thanks for confirming your email. Here is how most photographers get started:',
                '1. Create a gallery and upload a shoot. 2. Choose who can see it: public, PIN or invited clients only. 3. Share the link and watch the downloads and favorites come in.',
                'Your public portfolio is live at ' . $this->user->public_url . '. Add your bio and packages so clients can book you directly.',
            ],
            'button' => ['label' => 'Open your studio', 'url' => route('studio.dashboard')],
        ];
    }
}
