<x-email.layout title="Booking Confirmation">
    <x-email.heading 
        title="Booking Request Received" 
        subtitle="Your booking request has been delivered to {{ $photographer->name }}." 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $client->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Thank you for booking with {{ $photographer->name }}. Your booking request has been successfully recorded. Here is a summary of your requested session:
    </p>

    <x-email.card title="Your Booking Details">
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Session" :value="$booking->title" />
        @if($package)
            <x-email.detail-row label="Package" :value="$package->name" />
        @endif
        <x-email.detail-row label="Date & Time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        @if($booking->price)
            <x-email.detail-row label="Total" :value="number_format((float)$booking->price) . ' ' . $booking->currency" />
        @endif
        <x-email.detail-row label="Current Status" :value="ucfirst($booking->status)" />
    </x-email.card>

    <p style="font-size: 14px; color: #78716c; line-height: 1.6; margin: 0 0 24px;">
        {{ $photographer->name }} will review your appointment request and you will receive an email confirmation once the booking is accepted. If you have questions or need to reschedule, you can reply directly to this email or contact the photographer at <a href="mailto:{{ $photographer->email }}">{{ $photographer->email }}</a>.
    </p>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" label="Visit Photographer Portfolio" :showFallback="false" />
    @endif
</x-email.layout>
