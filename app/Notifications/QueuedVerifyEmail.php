<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $expireMinutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verify Email Address - ifotoset')
            ->action('Verify Email Address', $verificationUrl)
            ->view('emails.verify_email', [
                'userName' => $notifiable->name ?? 'there',
                'verificationUrl' => $verificationUrl,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
