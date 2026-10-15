<?php

namespace App\Listeners;

use App\Mail\AdminAlertMail;
use App\Models\User;
use App\Support\AdminAlerts;
use Illuminate\Auth\Events\Registered;

class NotifyAdminsOfSignup
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (!$user instanceof User) {
            return;
        }

        AdminAlerts::send(new AdminAlertMail(
            headline: 'New signup: ' . $user->name,
            summary: "{$user->name} ({$user->email}) created a studio.",
            details: array_filter([
                'Username' => $user->username,
                'Email' => $user->email,
                'Signed up' => now()->format('F j, Y g:i A') . ' UTC',
            ]),
            actionUrl: route('admin.users.index', ['search' => $user->email]),
            actionLabel: 'View user',
        ));
    }
}
