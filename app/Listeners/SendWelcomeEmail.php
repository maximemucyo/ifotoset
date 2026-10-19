<?php

namespace App\Listeners;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->email && !$user->isAdmin()) {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        }
    }
}
