<x-email.layout title="Booking Deposit Received">
    <x-email.heading 
        title="Deposit Payment Received" 
        subtitle="A client has successfully paid their booking deposit." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $photographer->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Good news! <strong>{{ $client->name }}</strong> has paid the booking deposit of <strong>{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for <strong>{{ $booking->title }}</strong>. The booking is now marked as <strong>Confirmed</strong>.
    </p>

    <x-email.card title="Deposit & Booking Details">
        <x-email.detail-row label="Client Name" :value="$client->name" />
        <x-email.detail-row label="Client Email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Client Phone" :value="$client->phone" />
        @endif
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Date & Time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        <x-email.detail-row label="Deposit Amount" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        <x-email.detail-row label="Provider" :value="$payment->provider" />
        <x-email.detail-row label="Reference" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" value="Confirmed" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="View in Studio Dashboard" />
</x-email.layout>
