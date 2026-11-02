<x-email.layout title="Verify your email" :preheader="'Confirm your email to start using ifotoset. The link works for ' . $expireMinutes . ' minutes.'">
    <x-email.heading
        title="Verify your email"
        subtitle="One click and your studio is ready."
    />

    <x-email.text>Hi {{ $userName }},</x-email.text>

    <x-email.text spacing="0">
        Confirm this is your email address so you can upload galleries, take bookings and deliver photos to clients.
    </x-email.text>

    <x-email.button :url="$verificationUrl" label="Verify email" />

    <x-email.text muted spacing="0">
        The link works for {{ $expireMinutes }} minutes. If you didn't create an ifotoset account, you can ignore this email.
    </x-email.text>
</x-email.layout>
