<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedResetPassword extends ResetPasswordNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Password Notification - ifotoset')
            ->action('Reset Password', $resetUrl)
            ->view('emails.reset_password', [
                'userName' => $notifiable->name ?? 'there',
                'resetUrl' => $resetUrl,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
