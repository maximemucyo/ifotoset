<x-email.layout title="Deposit received" :preheader="$client->name . ' paid a ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '.'">
    <x-email.heading
        title="Deposit received"
        :subtitle="$client->name . ' paid ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' for ' . $booking->title . '.'"
    />

    <x-email.text>Hi {{ $photographer->name }},</x-email.text>

    @if($booking->status->value === 'confirmed')
        <x-email.callout variant="success">
            The booking is confirmed.
        </x-email.callout>
    @else
        <x-email.callout variant="warning">
            The payment arrived after the time was booked by someone else, so this booking stays cancelled. Contact {{ $client->name }} to pick a new time or refund the deposit.
        </x-email.callout>
    @endif

    <x-email.card title="Booking">
        <x-email.detail-row label="Client" :value="$client->name" />
        <x-email.detail-row label="Email" :value="$client->email" />
        @if($client->phone)
            <x-email.detail-row label="Phone" :value="$client->phone" />
        @endif
        <x-email.detail-row label="Session" :value="$booking->title" />
        @if($booking->package)
            <x-email.detail-row label="Package" :value="$booking->package->name" />
        @endif
        <x-email.detail-row label="Date and time" :value="$booking->whenLabel()" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->notes)
            <x-email.detail-row label="Notes from the client" :value="$booking->notes" />
        @endif
        <x-email.detail-row label="Deposit" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        @if($booking->balanceDue())
            <x-email.detail-row label="Left to pay at the session" :value="number_format($booking->balanceDue()) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Paid with" :value="$payment->providerLabel()" />
        <x-email.detail-row label="Reference" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="Open the booking" :showFallback="false" />
</x-email.layout>
