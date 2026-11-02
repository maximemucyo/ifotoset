<x-email.layout title="Your verification code" :preheader="'Your verification code is ' . $code . '. It works for ' . $expiresInMinutes . ' minutes.'">
    <x-email.heading
        title="Confirm your new admin email"
        subtitle="Someone asked to change the email you use to sign in to the ifotoset admin."
    />

    <x-email.text>Hi {{ $admin->name }},</x-email.text>

    <x-email.text>
        Enter this code in the admin settings to make <strong class="em-h" style="color: #1c201e; font-weight: 600;">{{ $newEmail }}</strong> your sign-in email.
    </x-email.text>

    <x-email.code :code="$code" />

    <x-email.text muted spacing="0">
        The code works for {{ $expiresInMinutes }} minutes. If you didn't ask for this change, sign in now and change your admin password.
    </x-email.text>
</x-email.layout>
