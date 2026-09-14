<x-email.layout title="New Booking Request">
    <x-email.heading 
        title="New Booking Request" 
        subtitle="You have received a new booking request from {{ $client->name }}." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $photographer->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        A new booking has been scheduled through your public booking page. Here are the booking details:
    </p>

    <x-email.card title="Booking Summary">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Client Name" :value="$client->name" />
        <x-email.detail-row label="Client Email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Client Phone" :value="$client->phone" />
        @endif
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date & Time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Price" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
        @if($booking->notes)
            <x-email.detail-row label="Client Notes" :value="$booking->notes" />
        @endif
        <x-email.detail-row label="Status" :value="ucfirst($booking->status)" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="View in Studio Dashboard" />
</x-email.layout>
