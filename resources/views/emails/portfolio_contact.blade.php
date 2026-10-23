<x-email.layout title="New message from your portfolio" :preheader="$inquiry['name'] . ' sent you a message from your portfolio.'">
    <x-email.heading
        eyebrow="Portfolio"
        title="New message"
        subtitle="{{ $inquiry['name'] }} contacted you through your portfolio's contact page."
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    <x-email.text>Reply to this email to answer {{ $inquiry['name'] }} directly.</x-email.text>

    <x-email.card title="Message">
        <x-email.detail-row label="Name" :value="$inquiry['name']" />
        <x-email.detail-row label="Email" :value="$inquiry['email']" />
        @if(!empty($inquiry['phone']))
            <x-email.detail-row label="Phone / WhatsApp" :value="$inquiry['phone']" />
        @endif
        <x-email.detail-row label="Message" :value="$inquiry['message']" />
    </x-email.card>
</x-email.layout>
