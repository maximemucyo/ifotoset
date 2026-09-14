<x-email.layout title="Reset Your Password">
    <x-email.heading 
        title="Reset your password" 
        subtitle="You are receiving this email because we received a password reset request for your account." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hello {{ $userName ?? 'there' }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Click the button below to choose a new password for your ifotoset account:
    </p>

    <x-email.button :url="$resetUrl" label="Reset Password" />

    <x-email.card>
        <p style="margin: 0 0 8px; font-size: 13px; color: #78716c; line-height: 1.5;">
            <strong>Notice:</strong> This password reset link will expire in <strong>{{ $expireMinutes }} minutes</strong>.
        </p>
        <p style="margin: 0; font-size: 13px; color: #78716c; line-height: 1.5;">
            If you did not request a password reset, no further action is required and your account remains secure.
        </p>
    </x-email.card>
</x-email.layout>
