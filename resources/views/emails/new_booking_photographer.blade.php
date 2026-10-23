<x-email.layout title="New Booking Request" :preheader="$client->name . ' requested ' . $booking->title . ' on ' . $booking->localStartsAt()->format('l, F j \a\t g:i A') . '.'">
    <x-email.heading
        eyebrow="New booking"
        title="New booking request"
        subtitle="You have received a new booking request from {{ $client->name }}."
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    <x-email.text>
        A new booking was scheduled through your public booking page. Here are the details:
    </x-email.text>

    <x-email.card title="Booking summary">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Client name" :value="$client->name" />
        <x-email.detail-row label="Client email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Client phone" :value="$client->phone" />
        @endif
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date & time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Price" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
        @if($booking->notes)
            <x-email.detail-row label="Client notes" :value="$booking->notes" />
        @endif
        <x-email.detail-row label="Status" :value="ucfirst($booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status)" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="View in Studio" />
</x-email.layout>
