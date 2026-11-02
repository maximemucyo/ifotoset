<x-email.layout
    :title="$photographerName . ' shared a gallery with you'"
    :preheader="$photographerName . ' shared a private gallery with you: ' . $gallery->title"
    footerNote="You received this email because a photographer invited you to a private gallery on ifotoset."
>
    <x-email.banner :title="$gallery->title" :meta="'Shared with you by ' . $photographerName" note="Private gallery" />

    <x-email.text>
        {{ $photographerName }} shared this gallery with you. Open it to browse the photos, mark your favourites and download them.
    </x-email.text>

    <x-email.button :url="$inviteUrl" label="Open the gallery" />

    @if($gallery->expires_at)
        <x-email.callout variant="warning">
            This gallery closes on {{ $gallery->expires_at->toFormattedDateString() }}. Download anything you want to keep before then.
        </x-email.callout>
    @endif

    <x-email.text muted spacing="0">
        The link is personal to you, so please don't forward this email.
    </x-email.text>
</x-email.layout>
