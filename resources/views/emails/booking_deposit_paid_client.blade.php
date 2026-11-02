<x-email.layout title="Deposit receipt" :preheader="'Receipt for your ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '.'">
    <x-email.heading
        title="Deposit received"
        :subtitle="'Your deposit for ' . $booking->title . ' with ' . $photographer->name . ' went through.'"
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    @if($booking->status->value === 'confirmed')
        <x-email.callout variant="success">
            Your session is confirmed. A calendar invite is attached.
        </x-email.callout>
    @else
        <x-email.callout variant="warning">
            Someone else booked this time while your payment was going through. {{ $photographer->name }} will contact you to pick a new time or refund your deposit.
        </x-email.callout>
    @endif

    <x-email.card title="Receipt">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date and time" :value="$booking->whenLabel()" />
        <x-email.detail-row label="Deposit paid" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        @if($booking->balanceDue())
            <x-email.detail-row label="Left to pay at the session" :value="number_format($booking->balanceDue()) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Paid with" :value="$payment->providerLabel()" />
        <x-email.detail-row label="Reference" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Keep this email as your receipt. Questions? Email {{ $photographer->name }} at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>
</x-email.layout>
