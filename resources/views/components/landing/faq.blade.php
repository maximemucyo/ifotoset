@props(['items' => null, 'description' => 'Everything you need to know about the platform, delivery features and studio plans.'])
@php
$faqItems = $items ?? [
    [
        'question' => 'What is ifotoset?',
        'answer' => 'ifotoset is a complete, all-in-one photography platform built for modern photographers and studios in East Africa and beyond. It combines elegant client gallery delivery, portfolio showcase creation, and studio management tools (bookings, payments, and client management) into a single, unified experience.'
    ],
    [
        'question' => 'Who is ifotoset for?',
        'answer' => 'It is built for modern photographers, videographers, independent creatives, and studios—ranging from wedding and event photographers to portrait and commercial designers—who want a professional client delivery and studio booking system.'
    ],
    [
        'question' => 'How do client galleries work?',
        'answer' => 'You create a gallery inside your studio dashboard, upload your photos, and set privacy preferences. Your client receives an elegant password-protected gallery link where they can browse photos, download individual items or full sets, and share them directly.'
    ],
    [
        'question' => 'Can I password-protect galleries?',
        'answer' => 'Yes. Every client gallery can be protected with custom passwords to ensure only authorized viewers have access to your high-resolution photos.'
    ],
    [
        'question' => 'Can clients download their photos?',
        'answer' => 'Yes, if you allow it. For each gallery you decide whether clients can download single photos, the whole gallery as a ZIP file, or nothing at all.'
    ],
    [
        'question' => 'How much storage do I get?',
        'answer' => 'The Free plan includes 2 GB of storage. Basic includes 50 GB, Professional includes 1 TB, and Business provides 3 TB of optimized storage to handle high-volume RAW and high-resolution JPEG delivery.'
    ],
    [
        'question' => 'How does video hosting work?',
        'answer' => 'Video hosting is optimized for premium client delivery (such as highlight films, ceremony recordings, or commercial clips). Basic includes up to 30 minutes, Professional includes up to 5 hours, and Business includes up to 15 hours of total hosted video. All uploads are transcoded and optimized to ensure ultra-fast client playback.'
    ],
    [
        'question' => 'Can I customize my portfolio?',
        'answer' => 'Yes. Your portfolio lives on your own ifotoset subdomain. You choose which galleries appear on it and add your logo or profile photo, bio, location, packages and a booking page. Custom domains are coming soon on the Professional plan.'
    ],
    [
        'question' => 'Can I manage bookings and clients?',
        'answer' => 'Yes. Studio Manager handles packages, working hours, blocked time, client profiles and booking requests. Clients pick from your open time slots, and on Professional they can pay a deposit by Mobile Money when they book.'
    ],
    [
        'question' => 'Can I cancel anytime?',
        'answer' => 'Yes. ifotoset runs on a monthly or yearly subscription with no long-term contracts. You can cancel, upgrade, or downgrade your plan at any point in your billing settings.'
    ],
];
@endphp

<section id="faq" x-data="{ openIndex: 0 }" class="relative border-t border-border py-24 sm:py-32">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16 lg:px-8">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <x-landing.section-heading align="left" eyebrow="FAQ" :description="$description">
                Questions, <em class="font-serif font-normal italic tracking-normal">answered</em>.
            </x-landing.section-heading>
            <div class="mt-8 hidden items-center gap-3 rounded-xl border border-border bg-card p-4 lg:flex" data-reveal>
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="life-buoy" /></span>
                <p class="text-[13.5px] text-muted-foreground">Still unsure? Start on the free plan and explore everything at your own pace.</p>
            </div>
        </div>

        <div class="divide-y divide-border border-y border-border" data-reveal>
            @foreach($faqItems as $idx => $item)
                <div>
                    <h3 class="text-base">
                        <button
                            type="button"
                            id="faq-q-{{ $idx }}"
                            aria-controls="faq-a-{{ $idx }}"
                            @click="openIndex = (openIndex === {{ $idx }} ? null : {{ $idx }})"
                            :aria-expanded="(openIndex === {{ $idx }}).toString()"
                            class="group flex w-full items-center justify-between gap-6 py-5 text-left font-sans text-[15.5px] font-medium tracking-normal text-foreground transition-colors hover:text-foreground/80"
                        >
                            <span>{{ $item['question'] }}</span>
                            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition-[transform,background-color,color] duration-300"
                                :class="openIndex === {{ $idx }} ? 'rotate-45 bg-foreground text-background border-foreground' : 'group-hover:border-border-strong'">
                                <x-icon name="plus" class="size-3.5" />
                            </span>
                        </button>
                    </h3>
                    <div
                        id="faq-a-{{ $idx }}"
                        role="region"
                        aria-labelledby="faq-q-{{ $idx }}"
                        x-show="openIndex === {{ $idx }}"
                        x-collapse
                        @if($idx !== 0) x-cloak @endif
                    >
                        <p class="max-w-2xl pb-6 pr-10 text-[14.5px] leading-relaxed text-muted-foreground">{{ $item['answer'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
