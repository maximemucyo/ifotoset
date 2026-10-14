<x-email.layout
    title="You are invited to view a gallery"
    :preheader="$photographerName . ' shared a private gallery with you: ' . $gallery->title"
    footerNote="This email was sent to you because you were invited to view a private gallery on ifotoset."
>
    <x-email.heading
        eyebrow="Private gallery"
        title="You've been invited to a gallery"
        :subtitle="$photographerName . ' shared a private photo gallery with you on ifotoset.'"
    />

    <x-email.card>
        <p class="em-h" style="margin: 0; font-size: 17px; font-weight: 600; letter-spacing: -0.01em; color: #111113; line-height: 1.4;">{{ $gallery->title }}</p>
        <p class="em-muted" style="margin: 4px 0 0; font-size: 14px; color: #6b6b72; line-height: 1.5;">Shared by {{ $photographerName }}</p>
    </x-email.card>

    <x-email.text>
        This gallery is private and secured. Use the button below to view all photos. Please don't forward this email: the link contains your personal access token.
    </x-email.text>

    <x-email.button :url="$inviteUrl" label="View gallery" />

    @if($gallery->expires_at)
        <x-email.callout variant="warning">
            <strong>Heads up:</strong> this invitation expires on {{ $gallery->expires_at->toFormattedDateString() }}.
        </x-email.callout>
    @endif
</x-email.layout>
