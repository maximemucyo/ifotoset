<x-email.layout title="Verify Your Email Address">
    <x-email.heading 
        title="Verify your email address" 
        subtitle="Thank you for creating an account on ifotoset. Please verify your email address to unlock all features." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $userName }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        To activate your account and start uploading photo galleries, managing bookings, and delivering photos to clients, please verify your email address by clicking the button below:
    </p>

    <x-email.button :url="$verificationUrl" label="Verify Email Address" />

    <x-email.card>
        <p style="margin: 0; font-size: 13px; color: #78716c; line-height: 1.5;">
            <strong>Security Notice:</strong> This verification link will expire in <strong>{{ $expireMinutes }} minutes</strong>. If you did not create an account on ifotoset, please disregard this email.
        </p>
    </x-email.card>
</x-email.layout>
