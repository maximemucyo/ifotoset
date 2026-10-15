{{-- Shared template for short transactional notices (see App\Mail\NoticeMail). --}}
<x-email.layout :title="$notice['title']" :preheader="$notice['preheader'] ?? null">
    <x-email.heading
        :eyebrow="$notice['eyebrow'] ?? null"
        :title="$notice['title']"
        :subtitle="$notice['subtitle'] ?? null"
    />

    @if(!empty($notice['greeting']))
        <x-email.text>Hi {{ $notice['greeting'] }},</x-email.text>
    @endif

    @foreach($notice['paragraphs'] ?? [] as $paragraph)
        <x-email.text>{{ $paragraph }}</x-email.text>
    @endforeach

    @if(!empty($notice['callout']))
        <x-email.callout :variant="$notice['callout']['variant'] ?? 'neutral'">{{ $notice['callout']['text'] }}</x-email.callout>
    @endif

    @if(!empty($notice['details']))
        <x-email.card :title="$notice['detailsTitle'] ?? null">
            @foreach($notice['details'] as $label => $value)
                <x-email.detail-row :label="$label" :value="(string) $value" />
            @endforeach
        </x-email.card>
    @endif

    @if(!empty($notice['button']))
        <x-email.button :url="$notice['button']['url']" :label="$notice['button']['label']" />
    @endif

    @foreach($notice['footnotes'] ?? [] as $footnote)
        <x-email.text muted spacing="8px">{{ $footnote }}</x-email.text>
    @endforeach
</x-email.layout>
