<x-email.layout title="Verify Your Email Address" :preheader="'Confirm your email to activate your ifotoset account. The link expires in ' . $expireMinutes . ' minutes.'">
    <x-email.heading
        eyebrow="Welcome to ifotoset"
        title="Verify your email address"
        subtitle="Thanks for creating an account. Please verify your email address to unlock all features."
    />

    <x-email.text>Hi {{ $userName }},</x-email.text>

    <x-email.text spacing="0">
        To activate your account and start uploading galleries, managing bookings and delivering photos to clients, verify your email address with the button below.
    </x-email.text>

    <x-email.button :url="$verificationUrl" label="Verify email address" />

    <x-email.card>
        <p class="em-muted" style="margin: 0; font-size: 13px; color: #6e6159; line-height: 1.55;">
            This verification link expires in <strong class="em-h" style="color: #17110e;">{{ $expireMinutes }} minutes</strong>. If you did not create an account on ifotoset, you can safely ignore this email.
        </p>
    </x-email.card>
</x-email.layout>
