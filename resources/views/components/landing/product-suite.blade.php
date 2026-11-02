@php
    $tools = [
        [
            'screen' => 'gallery-mobile',
            'alt' => 'A client gallery on a phone',
            'title' => 'Client galleries',
            'body' => 'Upload photos and films, set who can see and download them, then send one link.',
            'route' => 'features.galleries',
            'link' => 'How galleries work',
        ],
        [
            'screen' => 'portfolio-mobile',
            'alt' => 'A photographer portfolio website on a phone',
            'title' => 'Portfolio website',
            'body' => 'Your best galleries on your own ifotoset address, with your packages and a booking page.',
            'route' => 'features.portfolio',
            'link' => 'How portfolios work',
        ],
        [
            'screen' => 'booking-mobile',
            'alt' => 'Booking a session on a phone',
            'title' => 'Online booking',
            'body' => 'Clients pick an open slot from your calendar. You confirm it from your studio.',
            'route' => 'features.booking',
            'link' => 'How booking works',
        ],
    ];
@endphp

<section id="features" class="scroll-mt-16 border-t border-border bg-hill/40 py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="headline">And this is where you run it from.</h2>
            <p class="mt-4 text-[17px] leading-relaxed text-muted-foreground">
                Three tools that usually mean three subscriptions, in one studio you can manage from your phone.
            </p>
        </div>
    </div>

    {{-- Swipe on phones, three columns from lg --}}
    <ul class="mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-4 [scrollbar-width:none] sm:scroll-px-6 sm:px-6 lg:mx-auto lg:grid lg:max-w-7xl lg:grid-cols-3 lg:gap-8 lg:overflow-visible lg:px-8 [&::-webkit-scrollbar]:hidden"
        aria-label="ifotoset tools">
        @foreach($tools as $tool)
            <li class="w-[78%] max-w-[300px] shrink-0 snap-start lg:w-auto lg:max-w-none">
                <x-marketing.phone :name="$tool['screen']" :alt="$tool['alt']" class="max-w-none lg:max-w-[260px]" />
                <h3 class="mt-6 text-[20px] font-semibold">{{ $tool['title'] }}</h3>
                <p class="mt-2 text-[15px] leading-relaxed text-muted-foreground">{{ $tool['body'] }}</p>
                <a href="{{ route($tool['route']) }}" class="mt-1 inline-flex min-h-11 items-center gap-1.5 text-[15px] font-medium text-foreground underline decoration-brand decoration-2 underline-offset-4 hover:decoration-foreground">
                    {{ $tool['link'] }} <x-icon name="chevron-right" class="size-4" />
                </a>
            </li>
        @endforeach
    </ul>
</section>
