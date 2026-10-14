<x-email.layout title="Booking Deposit Received" :preheader="$client->name . ' paid a ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '.'">
    <x-email.heading
        eyebrow="Payment received"
        title="Deposit payment received"
        subtitle="A client has successfully paid their booking deposit."
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    <x-email.text>
        Good news! <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $client->name }}</strong> paid the booking deposit of <strong class="em-h" style="color: #111113; font-weight: 600;">{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $booking->title }}</strong>. The booking is now marked as confirmed.
    </x-email.text>

    <x-email.card title="Deposit & booking details">
        <x-email.detail-row label="Client name" :value="$client->name" />
        <x-email.detail-row label="Client email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Client phone" :value="$client->phone" />
        @endif
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Date & time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        <x-email.detail-row label="Deposit amount" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        <x-email.detail-row label="Provider" :value="$payment->provider" />
        <x-email.detail-row label="Reference" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" value="Confirmed" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="View in Studio" />
</x-email.layout>
