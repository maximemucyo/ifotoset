<x-email.layout title="Booking Deposit Receipt" :preheader="'Receipt: ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '. Your booking is confirmed.'">
    <x-email.heading
        eyebrow="Receipt"
        title="Deposit payment confirmed"
        subtitle="Your booking deposit payment has been received successfully."
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    <x-email.text>
        Thank you! We've received your deposit of <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for your booking with {{ $photographer->name }}.
        @if($booking->status->value === 'confirmed')
            Your booking is now officially confirmed. A calendar invite is attached.
        @else
            The time you picked was taken while your payment was processing, so {{ $photographer->name }} will contact you to choose a new time or refund your deposit.
        @endif
    </x-email.text>

    <x-email.card title="Payment & session summary">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date & time" :value="$booking->whenLabel()" />
        <x-email.detail-row label="Deposit paid" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        @if($booking->balanceDue())
            <x-email.detail-row label="Balance due at the session" :value="number_format($booking->balanceDue()) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Payment method" :value="$payment->provider" />
        <x-email.detail-row label="Reference ID" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" :value="$booking->status->value === 'confirmed' ? 'Confirmed' : 'Needs a new time'" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Please keep this receipt for your records. If you have any questions, contact {{ $photographer->name }} at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>
</x-email.layout>
