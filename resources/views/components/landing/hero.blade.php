@php
    $img = fn (string $n) => asset("images/marketing/hero-{$n}.webp");
    // Column-first masonry order: alternate portrait / landscape across the top row
    $grid = [
        ['n' => 1, 'w' => 1000, 'h' => 1250, 'alt' => 'Couple on a bench at sunset'],
        ['n' => 3, 'w' => 1200, 'h' => 800, 'alt' => 'Tuscan farmhouse among cypress trees'],
        ['n' => 5, 'w' => 1200, 'h' => 800, 'alt' => 'Sunrise over a field and barn'],
        ['n' => 2, 'w' => 800, 'h' => 1000, 'alt' => 'Portrait of a woman holding wildflowers', 'fav' => true],
        ['n' => 13, 'w' => 800, 'h' => 1000, 'alt' => 'Hiker overlooking misty mountains'],
        ['n' => 11, 'w' => 1200, 'h' => 800, 'alt' => 'Seaside at sunset'],
        ['n' => 15, 'w' => 1200, 'h' => 800, 'alt' => 'City river at night'],
        ['n' => 10, 'w' => 800, 'h' => 1000, 'alt' => 'Pink blossoms', 'fav' => true],
    ];
    $genres = ['Weddings', 'Portraits', 'Events', 'Concerts', 'Commercial', 'Family', 'Travel', 'Fashion', 'Newborn', 'Graduation', 'Real estate', 'Videography'];
@endphp

<section class="relative isolate overflow-hidden">
    {{-- Backdrop --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[720px] bg-grid mask-radial opacity-60 dark:opacity-40"></div>
    <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-[-280px] -z-10 h-[560px] w-[min(1100px,140vw)] -translate-x-1/2 rounded-full bg-brand/25 blur-[120px] dark:bg-brand/14"></div>

    <div class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 sm:pt-20 lg:px-8 lg:pt-24">
        <div class="mx-auto max-w-4xl text-center">
            <a href="#features" class="group inline-flex animate-fade-up items-center gap-2 rounded-full border border-border bg-card/80 py-1 pl-1 pr-3 text-[13px] font-medium text-muted-foreground shadow-xs backdrop-blur transition-colors hover:border-border-strong hover:text-foreground">
                <span class="rounded-full bg-brand/10 px-2 py-0.5 text-[11px] font-semibold text-brand">New</span>
                Video delivery for highlight films
                <x-icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
            </a>

            <h1 class="mt-7 animate-fade-up text-[42px] font-semibold leading-[1.02] tracking-[-0.035em] text-foreground [animation-delay:60ms] sm:text-6xl lg:text-[76px]">
                Galleries your clients
                <em class="font-serif text-[1.08em] font-normal italic tracking-[-0.01em] text-brand">love</em>.
                <span class="block text-muted-foreground">A studio that runs itself.</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl animate-fade-up text-base leading-relaxed text-muted-foreground [animation-delay:120ms] sm:text-lg">
                ifotoset brings client galleries, your portfolio website, bookings and Mobile Money payments into one calm workspace, so you spend less time on admin and more time behind the camera.
            </p>

            <div class="mt-9 flex animate-fade-up flex-col items-stretch justify-center gap-3 [animation-delay:180ms] sm:flex-row sm:items-center">
                @auth
                    <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-xl">Open your studio <x-icon name="arrow-right" /></a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">Start free <x-icon name="arrow-right" /></a>
                @endauth
                <a href="#showcase" class="btn btn-outline btn-xl"><x-icon name="play" /> See how it works</a>
            </div>

            <ul class="mt-8 flex animate-fade-up flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[13px] text-muted-foreground [animation-delay:240ms]">
                <li class="inline-flex items-center gap-1.5"><x-icon name="circle-check" class="size-4 text-success" /> Free plan, no card required</li>
                <li class="inline-flex items-center gap-1.5"><x-icon name="smartphone" class="size-4 text-success" /> Mobile Money payments</li>
                <li class="inline-flex items-center gap-1.5"><x-icon name="circle-check" class="size-4 text-success" /> Cancel anytime</li>
            </ul>
        </div>

        {{-- Product composition: a client gallery in a browser frame --}}
        <div class="relative mx-auto mt-14 max-w-6xl animate-fade-up [animation-delay:320ms] sm:mt-20">
            <div aria-hidden="true" class="absolute -inset-x-6 -bottom-10 top-10 -z-10 rounded-[2rem] bg-gradient-to-b from-brand/10 via-transparent to-transparent blur-2xl"></div>

            <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-foreground/[0.03] dark:shadow-black/40">
                {{-- Browser chrome --}}
                <div class="flex items-center gap-3 border-b border-border bg-muted/60 px-3 py-2.5 sm:px-4">
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                    </div>
                    <div class="mx-auto flex h-7 w-full max-w-sm items-center justify-center gap-1.5 rounded-md border border-border bg-card px-3 text-[11.5px] text-muted-foreground">
                        <x-icon name="lock" class="size-3 text-success" />
                        <span class="truncate">yourstudio.ifotoset.com/golden-hour</span>
                    </div>
                    <div class="hidden w-[42px] sm:block" aria-hidden="true"></div>
                </div>

                {{-- Gallery cover --}}
                <div class="relative aspect-[4/3] overflow-hidden sm:aspect-[21/8]">
                    <img
                        src="{{ $img(8) }}"
                        width="1200" height="800"
                        alt="Golden sunset over a green field"
                        fetchpriority="high" decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-black/10"></div>
                    <div class="absolute inset-x-0 top-0 flex items-center justify-between px-4 py-3 text-white sm:px-6 sm:py-4">
                        <span class="font-display text-[13px] font-semibold tracking-tight">Your Studio</span>
                        <span class="hidden items-center gap-4 text-[12px] text-white/80 sm:flex">
                            <span>Gallery</span><span>Portfolio</span><span>Book a session</span>
                        </span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 flex flex-col items-start gap-3 p-5 text-white sm:flex-row sm:items-end sm:justify-between sm:p-8">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-white/70">Wedding collection</p>
                            <p class="mt-1 font-serif text-4xl italic leading-none sm:text-6xl">Golden Hour</p>
                        </div>
                        <span class="inline-flex h-9 items-center gap-2 rounded-lg bg-white/95 px-3.5 text-[13px] font-medium text-black shadow-sm">
                            <x-icon name="images" class="size-4" /> View gallery
                        </span>
                    </div>
                </div>

                {{-- Toolbar --}}
                <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-1 overflow-hidden text-[12.5px]">
                        <span class="rounded-md bg-muted px-2.5 py-1 font-medium text-foreground">All</span>
                        <span class="px-2.5 py-1 text-muted-foreground">Ceremony</span>
                        <span class="px-2.5 py-1 text-muted-foreground">Portraits</span>
                        <span class="hidden px-2.5 py-1 text-muted-foreground sm:inline">Reception</span>
                        <span class="hidden px-2.5 py-1 text-muted-foreground md:inline">Highlight film</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5 text-muted-foreground">
                        <span class="hidden size-8 items-center justify-center rounded-lg border border-border sm:inline-flex"><x-icon name="heart" class="size-4" /></span>
                        <span class="hidden size-8 items-center justify-center rounded-lg border border-border sm:inline-flex"><x-icon name="share-2" class="size-4" /></span>
                        <span class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-[12.5px] font-medium text-primary-foreground"><x-icon name="download" class="size-3.5" /> Download all</span>
                    </div>
                </div>

                {{-- Masonry grid --}}
                <div class="relative h-[380px] overflow-hidden p-3 sm:h-[460px] sm:p-4">
                    <div class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4">
                        @foreach($grid as $photo)
                            <figure class="group relative mb-3 break-inside-avoid overflow-hidden rounded-lg bg-muted sm:mb-4">
                                <img src="{{ $img($photo['n']) }}" width="{{ $photo['w'] }}" height="{{ $photo['h'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async" class="h-auto w-full transition-transform duration-700 group-hover:scale-[1.03]">
                                @if($photo['fav'] ?? false)
                                    <span class="absolute right-2 top-2 inline-flex size-7 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur"><x-icon name="heart" class="size-3.5 fill-current" /></span>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-card via-card/80 to-transparent"></div>
                </div>
            </div>

            {{-- Floating UI cards (illustrative) --}}
            <div aria-hidden="true" class="absolute -left-8 top-[64%] hidden w-64 rounded-xl border border-border bg-popover/95 p-3.5 shadow-lg backdrop-blur lg:block xl:-left-14">
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-9 items-center justify-center rounded-lg bg-success/10 text-success"><x-icon name="smartphone" class="size-4.5" /></span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-foreground">Payment received</p>
                        <p class="text-[12px] text-muted-foreground">Mobile Money · Deposit</p>
                    </div>
                    <x-icon name="circle-check" class="ml-auto size-4 text-success" />
                </div>
            </div>

            <div aria-hidden="true" class="absolute -right-6 top-[30%] hidden rounded-xl border border-border bg-popover/95 p-3.5 shadow-lg backdrop-blur lg:block xl:-right-12">
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="heart" class="size-4.5" /></span>
                    <div>
                        <p class="text-[13px] font-semibold text-foreground">New favourites</p>
                        <p class="whitespace-nowrap text-[12px] text-muted-foreground">Your client picked their prints</p>
                    </div>
                </div>
            </div>

            <div aria-hidden="true" class="absolute -right-4 bottom-16 hidden w-72 rounded-xl border border-border bg-popover/95 p-3.5 shadow-lg backdrop-blur lg:block xl:-right-10">
                <div class="flex items-center justify-between">
                    <p class="text-[13px] font-semibold text-foreground">Session booked</p>
                    <span class="badge badge-success badge-dot">Confirmed</span>
                </div>
                <div class="mt-2.5 flex items-center gap-2 text-[12px] text-muted-foreground">
                    <x-icon name="calendar" class="size-3.5" /> Saturday · 4:30 PM · Portrait session
                </div>
            </div>
        </div>
    </div>

    {{-- Genres marquee --}}
    <div class="mx-auto mt-16 max-w-7xl px-4 sm:mt-20 sm:px-6 lg:px-8">
        <p class="text-center text-[13px] text-muted-foreground">Made for photographers and studios of every kind</p>
        <div class="relative mt-5 overflow-hidden mask-fade-x" aria-hidden="true">
            <div class="flex w-max animate-marquee gap-3 hover:[animation-play-state:paused]">
                @foreach([...$genres, ...$genres] as $genre)
                    <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border border-border bg-card px-4 py-1.5 font-display text-[14px] font-medium tracking-tight text-muted-foreground">
                        <x-icon name="aperture" class="size-3.5 text-subtle" /> {{ $genre }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>
