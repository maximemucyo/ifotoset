<x-email.layout title="Booking Deposit Receipt" :preheader="'Receipt: ' . number_format((float)$payment->amount) . ' ' . $payment->currency . ' deposit for ' . $booking->title . '. Your booking is confirmed.'">
    <x-email.heading
        eyebrow="Receipt"
        title="Deposit payment confirmed"
        subtitle="Your booking deposit payment has been received successfully."
    />

    <x-email.text>Hi {{ $client->name }},</x-email.text>

    <x-email.text>
        Thank you! We've received your deposit of <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for your booking with {{ $photographer->name }}. Your booking is now officially confirmed.
    </x-email.text>

    <x-email.card title="Payment & session summary">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date & time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        <x-email.detail-row label="Deposit paid" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        <x-email.detail-row label="Payment method" :value="$payment->provider" />
        <x-email.detail-row label="Reference ID" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" value="Confirmed" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Please keep this receipt for your records. If you have any questions, contact {{ $photographer->name }} at <x-email.link :href="'mailto:' . $photographer->email">{{ $photographer->email }}</x-email.link>.
    </x-email.text>
</x-email.layout>
