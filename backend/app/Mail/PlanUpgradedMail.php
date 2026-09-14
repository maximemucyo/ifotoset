<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanUpgradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Payment $payment,
        public Plan $plan
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Subscription Payment Confirmed: Welcome to ifotoset {$this->plan->name}!",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return new Content(
            view: 'emails.plan_upgraded',
            with: [
                'user' => $this->user,
                'payment' => $this->payment,
                'plan' => $this->plan,
                'dashboardUrl' => "{$frontendUrl}/studio/dashboard",
            ],
        );
    }
}
