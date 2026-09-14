<x-email.layout title="Booking Status Update">
    <x-email.heading 
        :title="'Booking ' . ucfirst($newStatus)" 
        :subtitle="'Your booking status with ' . $photographer->name . ' has been updated.'" 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $client->name }},
    </p>

    @if($newStatus === 'confirmed')
        <p style="font-size: 15px; color: #15803d; font-weight: 600; line-height: 1.6; margin: 0 0 20px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px;">
            Great news! Your booking has been confirmed by {{ $photographer->name }}.
        </p>
    @elseif($newStatus === 'cancelled')
        <p style="font-size: 15px; color: #b91c1c; font-weight: 600; line-height: 1.6; margin: 0 0 20px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px 16px;">
            Your booking scheduled for {{ $booking->starts_at->format('l, F j, Y \a\t g:i A') }} has been cancelled.
        </p>
    @endif

    <x-email.card title="Booking Overview">
        <x-email.detail-row label="Session" :value="$booking->title" />
        <x-email.detail-row label="Photographer" :value="$photographer->name" />
        <x-email.detail-row label="Date & Time" :value="$booking->starts_at->format('l, F j, Y \a\t g:i A')" />
        @if($booking->location)
            <x-email.detail-row label="Location" :value="$booking->location" />
        @endif
        <x-email.detail-row label="New Status" :value="ucfirst($newStatus)" />
    </x-email.card>

    <p style="font-size: 14px; color: #78716c; line-height: 1.6; margin: 0 0 24px;">
        If you have questions or would like to contact {{ $photographer->name }}, you can email <a href="mailto:{{ $photographer->email }}">{{ $photographer->email }}</a>.
    </p>

    @if($portfolioUrl)
        <x-email.button :url="$portfolioUrl" label="Visit Photographer Profile" :showFallback="false" />
    @endif
</x-email.layout>
