<x-email.layout title="Booking Confirmation" :preheader="'Your request for ' . $booking->title . ' on ' . $booking->localStartsAt()->format('M j') . ' has been sent to ' . $photographer->name . '.'">
    <x-email.heading
        eyebrow="Booking request"
        title="Booking request received"
        subtitle="Your booking request has been delivered to {{ $photographer->name }}."
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    <x-email.text>
        Thank you for booking with {{ $photographer->name }}. Your request has been recorded. Here is a summary of the session you requested:
    </x-email.text>

    <x-email.card title="Your booking details">
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Session" :value="$booking->title" />
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date & time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Total" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Status" :value="ucfirst($booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status)" />
    </x-email.card>

    <x-email.text muted spacing="0">
        {{ $photographer->name }} will review your request and you'll receive another email once it's accepted. Questions or need to reschedule? Reply to this email or contact the photographer at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" label="Visit photographer portfolio" :showFallback="false" />
    @endif
</x-email.layout>
