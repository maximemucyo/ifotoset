<?php

namespace App\Listeners;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;

/**
 * PasswordReset is fired for both the forgot-password flow and in-app
 * password changes (see the settings controllers).
 */
class SendPasswordChangedEmail
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->email) {
            Mail::to($user->email)->queue(new PasswordChangedMail($user, request()?->ip()));
        }
    }
}
