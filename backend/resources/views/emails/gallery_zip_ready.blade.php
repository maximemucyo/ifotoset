<x-email.layout title="Your Photos are Ready for Download">
    <x-email.heading 
        title="Your Photos Are Ready" 
        :subtitle="'Your download package for ' . $download->gallery->title . ' has been prepared and is ready for download.'" 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi there,
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Great news! The photo archive you requested from <strong>{{ $download->gallery->user->name }}</strong> is packaged and ready. Click the button below to start your download.
    </p>

    <x-email.card title="Download Package Details">
        <x-email.detail-row label="Gallery" :value="$download->gallery->title" />
        <x-email.detail-row label="Photographer" :value="$download->gallery->user->name" />
        <x-email.detail-row label="Photos" :value="$download->processed_photos . ' ' . \Illuminate\Support\Str::plural('photo', $download->processed_photos)" />
        @if($download->size)
            <x-email.detail-row label="Archive Size" :value="round($download->size / (1024 * 1024), 1) . ' MB'" />
        @endif
        <x-email.detail-row label="Link Validity" value="Available for 24 hours" />
    </x-email.card>

    @if($download->failed_photos > 0)
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 14px 16px; font-size: 13px; color: #92400e; line-height: 1.5;">
                    <strong>Note:</strong> {{ $download->failed_photos }} photo(s) could not be included in this archive due to source extraction issues. The remaining {{ $download->processed_photos }} photos are packaged and ready.
                </td>
            </tr>
        </table>
    @endif

    <x-email.button :url="$downloadUrl" label="Download Photos (ZIP)" :showFallback="true" />

    <p style="font-size: 13px; color: #78716c; line-height: 1.6; margin: 24px 0 0; text-align: center;">
        This download link will expire in 24 hours. If it expires before you finish downloading, you can visit the gallery anytime to generate a fresh download.
    </p>

    @php
        $photographerUrl = app(\App\Services\PublicUrlService::class)->photographerUrl($download->gallery->user->username);
    @endphp
    @if($photographerUrl)
        <p style="font-size: 13px; color: #a8a29e; line-height: 1.5; margin: 16px 0 0; text-align: center;">
            View more work by <a href="{{ $photographerUrl }}" target="_blank" style="color: #dd7a53; text-decoration: underline;">{{ $download->gallery->user->name }}</a>.
        </p>
    @endif
</x-email.layout>
