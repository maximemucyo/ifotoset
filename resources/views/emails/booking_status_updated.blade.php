<x-email.layout :title="'Booking ' . $newStatus" :preheader="'Your booking ' . $booking->title . ' with ' . $photographer->name . ' is now ' . $newStatus . '.'">
    <x-email.heading
        :title="'Your booking is ' . $newStatus"
        :subtitle="$booking->title . ' with ' . $photographer->name"
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    @if($newStatus === 'confirmed')
        <x-email.callout variant="success">
            {{ $photographer->name }} confirmed your session on {{ $booking->whenLabel() }}.
        </x-email.callout>
    @elseif($newStatus === 'cancelled')
        <x-email.callout variant="danger">
            Your session on {{ $booking->whenLabel() }} was cancelled.
        </x-email.callout>
    @endif

    <x-email.card title="Booking">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date and time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        <x-email.detail-row label="Status" :value="ucfirst($newStatus)" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Questions? Write to {{ $photographer->name }} at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" :label="'See ' . $photographer->name . '\'s work'" :showFallback="false" />
    @endif
</x-email.layout>
