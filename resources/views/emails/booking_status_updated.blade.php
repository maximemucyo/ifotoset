<x-email.layout title="Booking Status Update" :preheader="'Your booking ' . $booking->title . ' with ' . $photographer->name . ' is now ' . $newStatus . '.'">
    <x-email.heading
        eyebrow="Booking update"
        :title="'Booking ' . ucfirst($newStatus)"
        :subtitle="'Your booking status with ' . $photographer->name . ' has been updated.'"
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    @if($newStatus === 'confirmed')
        <x-email.callout variant="success">
            <strong>Great news!</strong> Your booking has been confirmed by {{ $photographer->name }}.
        </x-email.callout>
    @elseif($newStatus === 'cancelled')
        <x-email.callout variant="danger">
            Your booking scheduled for {{ $booking->whenLabel() }} has been cancelled.
        </x-email.callout>
    @endif

    <x-email.card title="Booking overview">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date & time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        <x-email.detail-row label="New status" :value="ucfirst($newStatus)" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Questions, or want to get in touch with {{ $photographer->name }}? Email <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" label="Visit photographer profile" :showFallback="false" />
    @endif
</x-email.layout>
