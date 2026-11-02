<x-email.layout title="Booking request sent" :preheader="'Your request for ' . $booking->title . ' on ' . $booking->localStartsAt()->format('j M') . ' was sent to ' . $photographer->name . '.'">
    <x-email.heading
        title="Booking request sent"
        :subtitle="$photographer->name . ' has your request and will get back to you.'"
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    <x-email.text>
        Thanks for booking with {{ $photographer->name }}. You'll get another email as soon as they accept. Here's what you asked for:
    </x-email.text>

    <x-email.card title="Your request">
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Session" :value="$booking->title" />
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date and time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Total" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
    </x-email.card>

    <x-email.text muted spacing="0">
        Need to change something? Reply to this email or write to {{ $photographer->name }} at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" :label="'See ' . $photographer->name . '\'s work'" :showFallback="false" />
    @endif
</x-email.layout>
