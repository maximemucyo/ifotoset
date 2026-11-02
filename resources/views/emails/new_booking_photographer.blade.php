<x-email.layout title="New booking request" :preheader="$client->name . ' asked for ' . $booking->title . ' on ' . $booking->localStartsAt()->format('l j F \a\t g:i A') . '.'">
    <x-email.heading
        title="New booking request"
        :subtitle="$client->name . ' booked a session from your booking page.'"
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    <x-email.text>
        Accept or decline it from your studio. {{ $client->name }} gets an email either way.
    </x-email.text>

    <x-email.card title="Booking">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Client" :value="$client->name" />
        <x-email.detail-row label="Email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Phone" :value="$client->phone" />
        @endif
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date and time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Price" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
        @if($booking->notes)
            <x-email.detail-row label="Notes from the client" :value="$booking->notes" />
        @endif
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="Review the request" :showFallback="false" />
</x-email.layout>
