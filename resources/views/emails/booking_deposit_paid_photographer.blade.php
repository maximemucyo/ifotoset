<x-email.layout title="Booking Deposit Received" :preheader="$client->name . ' paid a ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '.'">
    <x-email.heading
        eyebrow="Payment received"
        title="Deposit payment received"
        subtitle="A client has successfully paid their booking deposit."
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    <x-email.text>
        Good news! <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ $client->name }}</strong> paid the booking deposit of <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ $booking->title }}</strong>.
        @if($booking->status->value === 'confirmed')
            The booking is now marked as confirmed.
        @else
            The payment arrived after the slot hold expired and the time had been booked by someone else, so the booking stays cancelled. Please contact the client to reschedule or refund the deposit.
        @endif
    </x-email.text>

    <x-email.card title="Deposit & booking details">
        <x-email.detail-row label="Client name" :value="$client->name" />
        <x-email.detail-row label="Client email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Client phone" :value="$client->phone" />
        @endif
        <x-email.detail-row label="Session" :value="$booking->title" />
        @if($booking->package)
            <x-email.detail-row label="Package" :value="$booking->package->name" />
        @endif
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->notes)
            <x-email.detail-row label="Client notes" :value="$booking->notes" />
        @endif
        <x-email.detail-row label="Date & time" :value="$booking->whenLabel()" />
        <x-email.detail-row label="Deposit amount" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        @if($booking->balanceDue())
            <x-email.detail-row label="Balance due at the session" :value="number_format($booking->balanceDue()) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Provider" :value="$payment->provider" />
        <x-email.detail-row label="Reference" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" :value="$booking->status->value === 'confirmed' ? 'Confirmed' : 'Cancelled: refund or reschedule'" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="View in Studio" />
</x-email.layout>
