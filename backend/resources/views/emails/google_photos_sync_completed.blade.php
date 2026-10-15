<x-email.layout
    title="Your photos have been synced to Google Photos"
    :preheader="$sync->processed_photos . ' of ' . $sync->total_photos . ' photos from ' . $sync->gallery->title . ' were exported to Google Photos.'"
    footerNote="This email was sent to you because you requested an export to Google Photos on ifotoset."
>
    <x-email.heading
        eyebrow="Google Photos"
        title="Export to Google Photos completed"
    />

    <x-email.text>
        We've finished exporting photos from the gallery <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ $sync->gallery->title }}</strong> to your Google Photos library.
    </x-email.text>

    <x-email.card :title="$sync->gallery->title">
        <x-email.detail-row label="Total selected" :value="$sync->total_photos . ' photos'" />
        <x-email.detail-row label="Successfully synced" :value="$sync->processed_photos" />
    </x-email.card>

    @if($sync->failed_photos > 0)
        <x-email.callout variant="warning">
            <strong>Note:</strong> {{ $sync->failed_photos }} photo(s) failed to sync. You can review the remaining {{ $sync->processed_photos }} in the album linked below.
        </x-email.callout>
    @endif

    <x-email.button :url="$sync->album_url" label="Open Google Photos album" />
</x-email.layout>
