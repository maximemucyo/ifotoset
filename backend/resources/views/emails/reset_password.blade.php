<x-email.layout title="Reset Your Password" :preheader="'Use this link to choose a new ifotoset password. It expires in ' . $expireMinutes . ' minutes.'">
    <x-email.heading
        title="Reset your password"
        subtitle="You are receiving this email because we received a password reset request for your account."
    />

    <x-email.text>Hello {{ $userName ?? 'there' }},</x-email.text>

    <x-email.text spacing="0">
        Click the button below to choose a new password for your ifotoset account.
    </x-email.text>

    <x-email.button :url="$resetUrl" label="Reset password" />

    <x-email.card>
        <p class="em-muted" style="margin: 0 0 6px; font-size: 13px; color: #6e6159; line-height: 1.55;">
            This password reset link expires in <strong class="em-h" style="color: #17110e;">{{ $expireMinutes }} minutes</strong>.
        </p>
        <p class="em-muted" style="margin: 0; font-size: 13px; color: #6e6159; line-height: 1.55;">
            If you did not request a password reset, no further action is required and your account remains secure.
        </p>
    </x-email.card>
</x-email.layout>
