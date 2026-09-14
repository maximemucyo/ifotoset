@php
$faqItems = [
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
        'answer' => 'Yes. You have control over download privileges. You can allow clients to download high-resolution or web-sized versions, apply watermarks, or disable downloads altogether based on your agreement.'
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
        'answer' => 'Yes. The portfolio builder lets you showcase curated galleries, add your brand logo, adjust theme styling, and set custom domain mapping on the Professional plan.'
    ],
    [
        'question' => 'Can I manage bookings and clients?',
        'answer' => 'Yes. Our integrated Studio Manager handles booking calendars, customizable packages, client contact profiles, and invoices in a central panel.'
    ],
    [
        'question' => 'Can I cancel anytime?',
        'answer' => 'Yes. ifotoset runs on a monthly or yearly subscription with no long-term contracts. You can cancel, upgrade, or downgrade your plan at any point in your billing settings.'
    ],
];
@endphp

<section id="faq" x-data="{ openIndex: null }" class="py-20 md:py-28 px-4 sm:px-6 lg:px-8 max-w-[1440px] mx-auto overflow-hidden border-t border-border/80">
    <div class="text-center max-w-3xl mx-auto mb-16">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
            Frequently Asked Questions
        </h2>
        <p class="text-lg text-muted-foreground">
            Everything you need to know about the platform, delivery features, and studio plans.
        </p>
    </div>

    <div class="max-w-3xl mx-auto space-y-4">
        @foreach($faqItems as $idx => $item)
            <div class="bg-card border border-border rounded-xl overflow-hidden transition-all duration-300">
                <!-- Accordion Trigger Header -->
                <button
                    type="button"
                    @click="openIndex = (openIndex === {{ $idx }} ? null : {{ $idx }})"
                    :aria-expanded="openIndex === {{ $idx }}"
                    class="w-full px-6 py-5 flex items-center justify-between text-left font-bold text-foreground text-sm sm:text-base outline-none focus-visible:bg-secondary/40 hover:bg-secondary/20 transition-colors"
                >
                    <span>{{ $item['question'] }}</span>
                    <svg
                        class="w-5 h-5 text-muted-foreground transition-transform duration-300"
                        :class="openIndex === {{ $idx }} ? 'rotate-180 text-primary' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Accordion Panel Body -->
                <div
                    x-show="openIndex === {{ $idx }}"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="border-t border-border/60"
                    style="display: none;"
                >
                    <div class="p-6 text-sm sm:text-base text-muted-foreground leading-relaxed">
                        {{ $item['answer'] }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
