@props(['items' => null, 'plans' => collect(), 'limit' => null, 'description' => 'Everything you need to know about the platform, delivery features and studio plans.'])
@php
// "A, B and C"
$sentenceList = fn ($parts) => collect($parts)->count() > 1 ? collect($parts)->slice(0, -1)->implode(', ') . ' and ' . collect($parts)->last() : (string) collect($parts)->first();

// Storage and video answers are built from the live Plan records, so admin edits show up here.
$storageAnswer = $plans->isEmpty() ? null : 'Storage depends on your plan: '
    . $sentenceList($plans->map(fn ($p) => $p->name . ' includes ' . $p->storageLabel())) . '.';
$videoPlans = $plans->filter(fn ($p) => $p->videoLabel());
$videoAnswer = $videoPlans->isEmpty() ? null : 'You can upload highlight films, ceremony recordings and other clips next to the photos in a gallery. '
    . ucfirst($sentenceList($videoPlans->map(fn ($p) => $p->name . ' includes up to ' . $p->videoLabel()))) . ' of video in total'
    . ($videoPlans->count() < $plans->count() ? ', and ' . $sentenceList($plans->reject(fn ($p) => $p->videoLabel())->pluck('name')) . ' ' . ($plans->count() - $videoPlans->count() === 1 ? 'is' : 'are') . ' for photo galleries only' : '')
    . '. We convert every video so it plays smoothly on phones and slow connections.';

$faqItems = $items ?? [
    // Most-asked first: the home page shows only the first few
    $storageAnswer ? [
        'question' => 'How much storage do I get?',
        'answer' => $storageAnswer,
    ] : null,
    $videoAnswer ? [
        'question' => 'How does video hosting work?',
        'answer' => $videoAnswer,
    ] : null,
    [
        'question' => 'Can clients download their photos?',
        'answer' => 'Yes, if you allow it. For each gallery you decide whether clients can download single photos, the whole gallery as a ZIP file, or nothing at all.'
    ],
    [
        'question' => 'Can I cancel anytime?',
        'answer' => 'Yes. Plans are monthly or yearly with no long-term contract. You can cancel, upgrade or downgrade at any time from your billing settings.'
    ],
    [
        'question' => 'How do client galleries work?',
        'answer' => 'Create a gallery in your studio, upload the photos and choose who can see them. Your client gets a link where they can browse, pick favourites and download single photos or the whole set.'
    ],
    [
        'question' => 'Who is ifotoset for?',
        'answer' => 'Photographers and videographers who deliver work to clients: weddings, events, portraits, family and commercial shoots. It works for one person on their own and for studios with a team.'
    ],
    [
        'question' => 'What is ifotoset?',
        'answer' => 'ifotoset is where photographers deliver galleries to clients, show their work on a portfolio website, and take bookings and Mobile Money deposits. It was built in East Africa and works anywhere.'
    ],
    [
        'question' => 'Can I password-protect galleries?',
        'answer' => 'Yes. Give any gallery a PIN, make it invite-only, or set an expiry date. Private galleries never appear on your portfolio or in search results.'
    ],
    [
        'question' => 'Can I customize my portfolio?',
        'answer' => 'Yes. Your portfolio lives on your own ifotoset subdomain. You choose which galleries appear on it and add your logo or profile photo, bio, location, packages and a booking page. Custom domains are coming soon on the Professional plan.'
    ],
    [
        'question' => 'Can I manage bookings and clients?',
        'answer' => 'Yes. Studio Manager handles packages, working hours, blocked time, client profiles and booking requests. Clients pick from your open time slots, and on Professional they can pay a deposit by Mobile Money when they book.'
    ],
];
$faqItems = array_values(array_filter($faqItems));
$moreItems = $limit && count($faqItems) > $limit;
$faqItems = $limit ? array_slice($faqItems, 0, $limit) : $faqItems;
@endphp

<section id="faq" x-data="{ openIndex: 0 }" class="scroll-mt-16 border-t border-border py-16 sm:py-24">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16 lg:px-8">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <h2 class="headline">Questions photographers ask.</h2>
            <p class="mt-4 max-w-md text-[17px] leading-relaxed text-muted-foreground">{{ $description }}</p>
        </div>

        <div>
            <div class="divide-y divide-border border-y border-border">
                @foreach($faqItems as $idx => $item)
                    <div>
                        <h3 class="text-base">
                            <button
                                type="button"
                                id="faq-q-{{ $idx }}"
                                aria-controls="faq-a-{{ $idx }}"
                                @click="openIndex = (openIndex === {{ $idx }} ? null : {{ $idx }})"
                                :aria-expanded="(openIndex === {{ $idx }}).toString()"
                                class="group flex min-h-14 w-full items-center justify-between gap-4 py-4 text-left font-sans text-[16px] font-medium tracking-normal text-foreground"
                            >
                                <span>{{ $item['question'] }}</span>
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition-[transform,background-color,color] duration-300"
                                    :class="openIndex === {{ $idx }} ? 'rotate-45 bg-foreground text-background border-foreground' : 'group-hover:border-border-strong'">
                                    <x-icon name="plus" class="size-4" />
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
                            <p class="max-w-2xl pb-5 text-[15px] leading-relaxed text-muted-foreground sm:pr-12">{{ $item['answer'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($moreItems)
                <a href="{{ route('pricing') }}#faq" class="mt-4 inline-flex min-h-11 items-center gap-1.5 text-[15px] font-medium underline decoration-brand decoration-2 underline-offset-4 hover:decoration-foreground">
                    See all questions <x-icon name="chevron-right" class="size-4" />
                </a>
            @endif
        </div>
    </div>
</section>
