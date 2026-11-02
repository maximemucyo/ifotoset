<x-email.layout title="Reset your password" :preheader="'Choose a new ifotoset password. The link works for ' . $expireMinutes . ' minutes.'">
    <x-email.heading
        title="Reset your password"
        subtitle="Someone asked to reset the password for your ifotoset account."
    />

    <x-email.text>Hi {{ $userName ?? 'there' }},</x-email.text>

    <x-email.text spacing="0">
        Use the button below to choose a new password.
    </x-email.text>

    <x-email.button :url="$resetUrl" label="Choose a new password" />

    <x-email.text muted spacing="0">
        The link works for {{ $expireMinutes }} minutes. If you didn't ask for this, you can ignore this email and your password stays the same.
    </x-email.text>
</x-email.layout>
