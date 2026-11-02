<x-email.layout title="Your photos are ready to download" :preheader="'Your download of ' . $download->gallery->title . ' is ready. The link works for 24 hours.'">
    <x-email.banner :title="$download->gallery->title" :meta="'By ' . $download->gallery->user->name" note="Your download is ready" />

    <x-email.text>
        The photos you asked for are packed into one ZIP file. The download link works for 24 hours.
    </x-email.text>

    <x-email.card title="Download details">
        <x-email.detail-row label="Photos" :value="$download->processed_photos . ' ' . \Illuminate\Support\Str::plural('photo', $download->processed_photos)" />
        @if($download->size)
            <x-email.detail-row label="File size" :value="\App\Support\Bytes::format($download->size)" />
        @endif
        <x-email.detail-row label="Link works for" value="24 hours" />
    </x-email.card>

    @if($download->failed_photos > 0)
        <x-email.callout variant="warning">
            {{ $download->failed_photos }} {{ \Illuminate\Support\Str::plural('photo', $download->failed_photos) }} couldn't be added to the ZIP. The other {{ $download->processed_photos }} are included.
        </x-email.callout>
    @endif

    <x-email.button :url="$downloadUrl" label="Download photos" />

    <x-email.text muted spacing="0">
        If the link runs out before you finish, open the gallery again and start a new download.
    </x-email.text>

    @php
        $photographerUrl = app(\App\Services\PublicUrlService::class)->photographerUrl($download->gallery->user->username);
    @endphp
    @if($photographerUrl)
        <x-email.text muted spacing="0">
            <br>See more work by <x-email.link :href="$photographerUrl">{{ $download->gallery->user->name }}</x-email.link>.
        </x-email.text>
    @endif
</x-email.layout>
