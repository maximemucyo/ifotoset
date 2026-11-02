<x-email.layout
    title="Your photos are in Google Photos"
    :preheader="$sync->processed_photos . ' of ' . $sync->total_photos . ' photos from ' . $sync->gallery->title . ' were saved to Google Photos.'"
    footerNote="You received this email because you saved a gallery to Google Photos on ifotoset."
>
    <x-email.heading
        title="Your photos are in Google Photos"
        :subtitle="'We saved the photos from ' . $sync->gallery->title . ' to an album in your Google Photos library.'"
    />

    <x-email.card>
        <x-email.detail-row label="Photos selected" :value="(string) $sync->total_photos" />
        <x-email.detail-row label="Saved" :value="(string) $sync->processed_photos" />
    </x-email.card>

    @if($sync->failed_photos > 0)
        <x-email.callout variant="warning">
            {{ $sync->failed_photos }} {{ \Illuminate\Support\Str::plural('photo', $sync->failed_photos) }} couldn't be saved. The other {{ $sync->processed_photos }} are in the album.
        </x-email.callout>
    @endif

    <x-email.button :url="$sync->album_url" label="Open the album" :showFallback="false" />
</x-email.layout>
