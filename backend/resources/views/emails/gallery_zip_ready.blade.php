<x-email.layout title="Your Photos are Ready for Download" :preheader="'Your ZIP of ' . $download->gallery->title . ' is ready. The link is valid for 24 hours.'">
    <x-email.heading
        eyebrow="Download ready"
        title="Your photos are ready"
        :subtitle="'Your download package for ' . $download->gallery->title . ' has been prepared and is ready for download.'"
    />

    <x-email.text>Hi there,</x-email.text>

    <x-email.text>
        The photo archive you requested from <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ $download->gallery->user->name }}</strong> is packaged and ready. Click the button below to start your download.
    </x-email.text>

    <x-email.card title="Download details">
        <x-email.detail-row label="Gallery" :value="$download->gallery->title" />
        <x-email.detail-row label="Photographer" :value="$download->gallery->user->name" />
        <x-email.detail-row label="Photos" :value="$download->processed_photos . ' ' . \Illuminate\Support\Str::plural('photo', $download->processed_photos)" />
        @if($download->size)
            <x-email.detail-row label="Archive size" :value="round($download->size / (1024 * 1024), 1) . ' MB'" />
        @endif
        <x-email.detail-row label="Link validity" value="Available for 24 hours" />
    </x-email.card>

    @if($download->failed_photos > 0)
        <x-email.callout variant="warning">
            <strong>Note:</strong> {{ $download->failed_photos }} photo(s) could not be included in this archive due to source extraction issues. The remaining {{ $download->processed_photos }} photos are packaged and ready.
        </x-email.callout>
    @endif

    <x-email.button :url="$downloadUrl" label="Download photos (ZIP)" :showFallback="true" />

    <x-email.text muted size="13px" spacing="0">
        This download link expires in 24 hours. If it expires before you finish, you can visit the gallery anytime to generate a fresh download.
    </x-email.text>

    @php
        $photographerUrl = app(\App\Services\PublicUrlService::class)->photographerUrl($download->gallery->user->username);
    @endphp
    @if($photographerUrl)
        <x-email.text muted size="13px" spacing="0">
            <br>View more work by <x-email.link :href="$photographerUrl">{{ $download->gallery->user->name }}</x-email.link>.
        </x-email.text>
    @endif
</x-email.layout>
