<x-email.layout title="New message from your portfolio" :preheader="$inquiry['name'] . ' sent you a message from your portfolio.'">
    <x-email.heading
        :title="'Message from ' . $inquiry['name']"
        subtitle="Sent from the contact form on your portfolio."
    />

    <x-email.callout>{!! nl2br(e($inquiry['message'])) !!}</x-email.callout>

    <x-email.card title="Contact details">
        <x-email.detail-row label="Name" :value="$inquiry['name']" />
        <x-email.detail-row label="Email" :value="$inquiry['email']" />
        @if(!empty($inquiry['phone']))
            <x-email.detail-row label="Phone or WhatsApp" :value="$inquiry['phone']" />
        @endif
    </x-email.card>

    <x-email.text muted spacing="0">Reply to this email to answer {{ $inquiry['name'] }} directly.</x-email.text>
</x-email.layout>
