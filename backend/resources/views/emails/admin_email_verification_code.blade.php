<x-email.layout title="Admin Email Verification Code">
    <x-email.heading 
        title="Admin Email Verification" 
        subtitle="A request was made to update the administrator login email for your ifotoset account." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $admin->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 24px;">
        Please use the 6-digit verification code below in your administrative settings to confirm this new email address (<strong>{{ $newEmail }}</strong>):
    </p>

    <div style="text-align: center; margin: 28px 0; padding: 24px; background-color: #f5f5f4; border: 2px dashed #d6d3d1; border-radius: 16px;">
        <span style="font-family: monospace; font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #1c1917;">
            {{ $code }}
        </span>
    </div>

    <x-email.card>
        <p style="margin: 0; font-size: 13px; color: #78716c; line-height: 1.5;">
            <strong>Security Notice:</strong> This code will expire in <strong>{{ $expiresInMinutes }} minutes</strong>. For your security, this request required current administrative credentials. If you did not request this email change, please log in immediately and rotate your administrator password.
        </p>
    </x-email.card>
</x-email.layout>
