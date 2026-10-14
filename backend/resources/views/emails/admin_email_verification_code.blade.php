<x-email.layout title="Admin Email Verification Code" :preheader="'Your verification code is ' . $code . '. It expires in ' . $expiresInMinutes . ' minutes.'">
    <x-email.heading
        eyebrow="Security"
        title="Confirm your new admin email"
        subtitle="A request was made to update the administrator login email for your ifotoset account."
    />

    <x-email.text>Hi {{ $admin->name }},</x-email.text>

    <x-email.text>
        Enter this 6-digit code in your administrative settings to confirm <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $newEmail }}</strong> as your new login email.
    </x-email.text>

    <x-email.code :code="$code" />

    <x-email.text muted size="13px" spacing="0">
        This code expires in <strong>{{ $expiresInMinutes }} minutes</strong>. For your security, this request required current administrative credentials. If you did not request this email change, log in immediately and rotate your administrator password.
    </x-email.text>
</x-email.layout>
