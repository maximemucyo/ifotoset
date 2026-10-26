@extends('layouts.marketing')

@php
    $googlePhotos = config('services.google.photos_export');
    $faq = [
        [
            'question' => 'How do I send a gallery to my client?',
            'answer' => 'Upload the photos, then copy the gallery link and send it by WhatsApp, SMS or email. You can also invite clients by email from the gallery itself, one by one or by importing a list, and resend or revoke an invitation at any time.',
        ],
        [
            'question' => 'Can clients download full-resolution photos?',
            'answer' => 'Yes, if you allow it. Each gallery has its own settings for downloading single photos and for downloading the whole gallery as a ZIP file. Turn both off for proofing-only galleries.',
        ],
        [
            'question' => 'How do I keep a gallery private?',
            'answer' => 'Make the gallery private so only invited people can open it, or protect it with a PIN and an optional hint. You can also set an expiry date. Private and PIN-protected galleries are never listed on your portfolio or shown to search engines.',
        ],
        [
            'question' => 'Do clients need an account?',
            'answer' => 'No. Clients open the link in any browser, on a phone or a computer. There is no app to install and no sign-up.',
        ],
        [
            'question' => 'Can I deliver videos too?',
            'answer' => 'Yes. On paid plans you can upload highlight films and other clips next to the photos. We convert them so they play smoothly on phones and slow connections.',
        ],
    ];
@endphp

@section('page')
    <x-marketing.hero eyebrow="Client galleries" description="Deliver every wedding, portrait and event in a private gallery that loads fast on any phone. Your clients browse, pick favourites and download the full set, with no app and no sign-up.">
        Online photo galleries your clients will <em class="font-serif font-normal italic tracking-[-0.01em] text-brand">love</em>.

        <x-slot:media>
            <x-marketing.screenshot name="gallery-client" :dark="false" priority url="yourstudio.ifotoset.com/wedding-sarah-john" alt="A client gallery on ifotoset: a wedding cover photo, a toolbar with favourites, download, share and slideshow, and a grid of photos" />
        </x-slot:media>
    </x-marketing.hero>

    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl space-y-24 px-4 sm:space-y-32 sm:px-6 lg:px-8">
            <x-marketing.feature-row
                eyebrow="Delivery"
                title="The whole shoot, in one link"
                :points="array_values(array_filter([
                    'Full-resolution downloads, one photo or the whole gallery',
                    'Favourites, so clients can shortlist prints and albums',
                    'Slideshow and share buttons built in',
                    $googlePhotos ? 'Save to Google Photos in one click' : null,
                ]))"
            >
                <p>Forget sending files through WeTransfer or Google Drive. Each gallery opens on your cover photo, then a clean grid of the full set, with your studio name at the top.</p>
                <p>Clients mark favourites as they browse, so you know which photos to print or put in the album without a long message thread.</p>

                <x-slot:media>
                    <div class="relative flex justify-center rounded-3xl border border-border bg-muted/40 px-6 py-10 sm:py-14">
                        <div aria-hidden="true" class="absolute inset-0 rounded-3xl bg-dots opacity-60 mask-radial"></div>
                        <x-marketing.phone name="gallery-mobile" :dark="false" class="relative" alt="A client gallery on a phone: the gallery title and a two-column photo grid" />
                    </div>
                </x-slot:media>
            </x-marketing.feature-row>

            <x-marketing.feature-row
                reverse
                eyebrow="Privacy"
                title="Private by default, public when you choose"
                :points="[
                    'Invite-only galleries with email invitations',
                    'PIN protection with an optional hint',
                    'Expiry dates for time-limited deliveries',
                    'Download permissions per gallery',
                ]"
            >
                <p>Every gallery has its own privacy settings. Keep a family session invite-only, put a PIN on a corporate event, or make a styled shoot public and feature it on your portfolio.</p>
                <p>Public galleries can appear on your portfolio and in search results. Private and PIN-protected ones never do.</p>

                <x-slot:media>
                    <x-marketing.screenshot name="gallery-studio" url="ifotoset.com/studio/galleries" alt="The gallery manager in the ifotoset studio, showing a gallery marked Public, its share link, tabs for clients and invitations and settings, and its photos" />
                </x-slot:media>
            </x-marketing.feature-row>
        </div>
    </section>

    <section class="border-y border-border bg-muted/40 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading eyebrow="Details" description="The small things that make a delivery feel professional.">
                Made for how clients actually <em class="font-serif font-normal italic tracking-normal">look</em> at photos.
            </x-landing.section-heading>

            <div class="mt-14 grid gap-4 md:grid-cols-3">
                @foreach([
                    ['icon' => 'smartphone', 'title' => 'Fast on mobile data', 'body' => 'Every photo is resized for the screen it is viewed on, with a soft placeholder while it loads. Galleries stay quick on 4G.'],
                    ['icon' => 'video', 'title' => 'Video delivery', 'body' => 'Upload highlight films next to the photos on paid plans. We convert them for smooth playback on any device.'],
                    ['icon' => 'chart-column', 'title' => 'Views and downloads', 'body' => 'See which galleries clients open and how many photos they download, so you know when to follow up.'],
                ] as $item)
                    <article class="card rounded-2xl p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon :name="$item['icon']" /></span>
                        <h3 class="mt-4 text-[16px] font-semibold tracking-tight">{{ $item['title'] }}</h3>
                        <p class="mt-1.5 text-[14px] leading-relaxed text-muted-foreground">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-landing.faq :items="$faq" description="Common questions about delivering photos with ifotoset." />
    <x-marketing.related current="galleries" />
@endsection
