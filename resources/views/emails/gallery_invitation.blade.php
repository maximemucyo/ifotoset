@php
    $isPublic = $gallery->visibility === 'public';
@endphp
<x-email.layout
    :title="$photographerName . ' shared a gallery with you'"
    :preheader="$photographerName . ' shared a gallery with you: ' . $gallery->title"
    footerNote="You received this email because a photographer shared a gallery with you on ifotoset."
>
    <x-email.banner :title="$gallery->title" :meta="'Shared with you by ' . $photographerName" :note="$isPublic ? null : 'Private gallery'" />

    <x-email.text>
        {{ $photographerName }} shared this gallery with you. Open it to browse the photos, mark your favourites and download them.
    </x-email.text>

    <x-email.button :url="$inviteUrl" label="Open the gallery" />

    @if($gallery->expires_at)
        <x-email.callout variant="warning">
            This gallery closes on {{ $gallery->expires_at->copy()->setTimezone(\App\Models\Gallery::expiryTimezone($gallery->user))->toFormattedDateString() }}. Download anything you want to keep before then.
        </x-email.callout>
    @endif

    @unless($isPublic)
        <x-email.text muted spacing="0">
            The link is personal to you, so please don't forward this email.
        </x-email.text>
    @endunless
</x-email.layout>
