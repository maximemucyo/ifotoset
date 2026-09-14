<x-email.layout title="Booking Deposit Receipt">
    <x-email.heading 
        title="Deposit Payment Confirmed" 
        subtitle="Your booking deposit payment has been received successfully." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $client->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Thank you! We have received your deposit payment of <strong>{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong> for your booking with {{ $photographer->name }}. Your booking is now officially confirmed.
    </p>

    <x-email.card title="Payment & Session Summary">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date & Time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        <x-email.detail-row label="Deposit Paid" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        <x-email.detail-row label="Payment Method" :value="$payment->provider" />
        <x-email.detail-row label="Reference ID" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Status" value="Confirmed" />
    </x-email.card>

    <p style="font-size: 14px; color: #78716c; line-height: 1.6; margin: 0 0 24px;">
        Please keep this receipt for your records. If you have any inquiries, feel free to contact {{ $photographer->name }} at <a href="mailto:{{ $photographer->email }}">{{ $photographer->email }}</a>.
    </p>
</x-email.layout>
