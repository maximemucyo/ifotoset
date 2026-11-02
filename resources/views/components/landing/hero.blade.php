@php
    $img = fn (string $n) => asset("images/marketing/hero-{$n}.webp");
    // The sample gallery: one lead photo, four more on phones, eight more from sm up
    $lead = ['n' => 1, 'w' => 1000, 'h' => 1250, 'alt' => 'Couple on a bench looking out at the hills'];
    $grid = [
        ['n' => 2, 'alt' => 'Portrait of a woman holding wildflowers', 'fav' => true],
        ['n' => 5, 'alt' => 'Sunrise over a field and barn'],
        ['n' => 13, 'alt' => 'Hiker overlooking misty mountains'],
        ['n' => 14, 'alt' => 'Misty hills at sunrise', 'fav' => true],
        ['n' => 3, 'alt' => 'Farmhouse among cypress trees', 'wide' => true],
        ['n' => 11, 'alt' => 'Seaside at sunset', 'wide' => true],
        ['n' => 10, 'alt' => 'Pink blossoms', 'wide' => true],
        ['n' => 12, 'alt' => 'Grapes on the vine', 'wide' => true],
    ];
    $facts = [
        ['icon' => 'lock', 'title' => 'Private by default', 'body' => 'Set a PIN, an expiry date and download rules for each gallery.'],
        ['icon' => 'heart', 'title' => 'Favourites', 'body' => 'Clients heart the photos they want printed or retouched.'],
        ['icon' => 'download', 'title' => 'Full-resolution downloads', 'body' => 'One photo or the whole set as a ZIP, if you allow it.'],
    ];
@endphp

{{-- Cover: the page opens the way an ifotoset gallery does. -mt-16 slides it under the sticky header. --}}
<section class="relative isolate -mt-16 flex min-h-[100svh] flex-col justify-end overflow-hidden bg-kivu text-white">
    <img
        src="{{ $img(8) }}"
        width="1200" height="800"
        alt="Golden sunset over a green field with a line of trees"
        fetchpriority="high" decoding="async"
        class="absolute inset-0 -z-10 size-full object-cover object-[60%_center]"
    >
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-t from-black/80 via-black/25 to-black/35"></div>

    <div class="mx-auto w-full max-w-7xl px-4 pb-10 pt-28 sm:px-6 sm:pb-16 lg:px-8 lg:pb-20">
        <p class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-black/30 px-3 py-1.5 text-[13px] text-white/90 backdrop-blur">
            <x-icon name="lock" class="size-3.5 shrink-0" />
            <span class="truncate">yourstudio.ifotoset.com/golden-hour</span>
        </p>

        <h1 class="headline-cover mt-5 max-w-[11ch] animate-fade-up text-white">Galleries your clients love to open.</h1>

        <p class="mt-5 max-w-md text-[17px] leading-relaxed text-white/85 sm:text-lg">
            Deliver photos and films, show your portfolio and take Mobile Money deposits, all from one studio.
        </p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
            @auth
                <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-xl">Open your studio</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary btn-xl">Start free</a>
            @endauth
            <a href="#gallery" class="btn btn-xl border-white/40 bg-white/10 text-white backdrop-blur hover:bg-white/20">See a sample gallery</a>
        </div>
        <p class="mt-4 text-[14px] text-white/75">Free plan, no card required.</p>
    </div>
</section>

{{-- What the client sees after tapping the link --}}
<section id="gallery" class="scroll-mt-16 py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="headline">This is what your client opens.</h2>
            <p class="mt-4 text-[17px] leading-relaxed text-muted-foreground">
                A private link with no app to install and no account to create. They browse, pick favourites and download.
            </p>
        </div>

        {{-- Gallery header, as it appears in a real client gallery --}}
        <div class="mt-10 flex items-end justify-between gap-4 border-b border-border pb-4">
            <div>
                <p class="font-headline text-[22px] font-semibold leading-tight">Golden Hour</p>
                <p class="mt-0.5 text-[14px] text-muted-foreground">248 photos and a highlight film</p>
            </div>
            <span class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg bg-primary px-3.5 text-[14px] font-medium text-primary-foreground" aria-hidden="true">
                <x-icon name="download" class="size-4" /> Download all
            </span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-1.5 sm:grid-cols-4 sm:gap-2">
            <figure class="relative col-span-2 aspect-[3/2] overflow-hidden rounded-md bg-muted sm:row-span-2 sm:aspect-auto">
                <img src="{{ $img($lead['n']) }}" width="{{ $lead['w'] }}" height="{{ $lead['h'] }}" alt="{{ $lead['alt'] }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
            </figure>
            @foreach($grid as $photo)
                <figure @class([
                    'relative aspect-square overflow-hidden rounded-md bg-muted',
                    'hidden sm:block' => $photo['wide'] ?? false,
                ])>
                    <img src="{{ $img($photo['n']) }}" width="800" height="800" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
                    @if($photo['fav'] ?? false)
                        <span class="absolute right-2 top-2 inline-flex size-8 items-center justify-center rounded-full bg-black/45 text-brand backdrop-blur">
                            <x-icon name="heart" class="size-4 fill-current" />
                            <span class="sr-only">Favourited by the client</span>
                        </span>
                    @endif
                </figure>
            @endforeach
        </div>

        <dl class="mt-10 grid gap-6 sm:grid-cols-3 sm:gap-8">
            @foreach($facts as $fact)
                <div class="flex gap-3">
                    <x-icon :name="$fact['icon']" class="mt-0.5 size-5 shrink-0 text-brand-strong" />
                    <div>
                        <dt class="text-[16px] font-semibold">{{ $fact['title'] }}</dt>
                        <dd class="mt-1 text-[15px] leading-relaxed text-muted-foreground">{{ $fact['body'] }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>
    </div>
</section>
