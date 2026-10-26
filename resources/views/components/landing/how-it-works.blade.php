@php
    $steps = [
        ['icon' => 'user-plus', 'title' => 'Create your studio', 'body' => 'Sign up for free, choose your username and add your logo or profile photo. Your portfolio is live straight away.'],
        ['icon' => 'cloud-upload', 'title' => 'Upload and organise', 'body' => 'Drag in photos and films. We optimise them for the web and keep your originals safe.'],
        ['icon' => 'send', 'title' => 'Deliver and get paid', 'body' => 'Send a private link, take bookings and collect Mobile Money payments in the same place.'],
    ];
@endphp

<section id="how-it-works" class="relative border-t border-border py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-heading eyebrow="How it works" description="No setup calls, no plugins, no learning curve.">
            From sign-up to first delivery in an <em class="font-serif font-normal italic tracking-normal">afternoon</em>.
        </x-landing.section-heading>

        <ol class="relative mt-16 grid gap-10 md:grid-cols-3 md:gap-8">
            <div aria-hidden="true" class="absolute left-[16.66%] right-[16.66%] top-6 hidden h-px bg-gradient-to-r from-transparent via-border-strong to-transparent md:block"></div>
            @foreach($steps as $i => $step)
                <li class="relative flex flex-col items-center text-center" data-reveal style="--reveal-delay: {{ $i * 120 }}ms">
                    <span class="relative inline-flex size-12 items-center justify-center rounded-2xl border border-border bg-card text-foreground shadow-sm">
                        <x-icon :name="$step['icon']" class="size-5" />
                        <span class="absolute -right-2 -top-2 inline-flex size-5 items-center justify-center rounded-full bg-brand-strong font-display text-[11px] font-semibold text-brand-foreground tabular-nums">{{ $i + 1 }}</span>
                    </span>
                    <h3 class="mt-6 text-lg font-semibold tracking-tight">{{ $step['title'] }}</h3>
                    <p class="mt-2 max-w-xs text-[14.5px] leading-relaxed text-muted-foreground">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
