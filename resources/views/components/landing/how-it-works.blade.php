@php
    $steps = [
        ['title' => 'Create your studio', 'body' => 'Sign up for free, choose your username and add your logo. Your portfolio is live straight away.'],
        ['title' => 'Upload and organise', 'body' => 'Drag in photos and films. We prepare them for the web and keep your originals safe.'],
        ['title' => 'Deliver and get paid', 'body' => 'Send a private link, take bookings and collect Mobile Money deposits in the same place.'],
    ];
@endphp

<section id="how-it-works" class="scroll-mt-16 border-t border-border py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="headline max-w-2xl">From sign-up to first delivery in an afternoon.</h2>

        <ol class="mt-10 grid gap-8 md:grid-cols-3 md:gap-10">
            @foreach($steps as $i => $step)
                <li class="flex gap-4 md:flex-col md:gap-3">
                    <span class="font-headline text-[44px] font-bold leading-none text-brand-strong tabular-nums md:text-[56px]" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="text-[19px] font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 max-w-sm text-[15px] leading-relaxed text-muted-foreground">{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
