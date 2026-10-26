@props(['current'])
@php
    $pages = [
        'galleries' => ['route' => 'features.galleries', 'icon' => 'images', 'title' => 'Client galleries', 'body' => 'Private, fast galleries for delivering photos and films.'],
        'portfolio' => ['route' => 'features.portfolio', 'icon' => 'globe', 'title' => 'Portfolio website', 'body' => 'A site for your best work, with packages and booking built in.'],
        'booking' => ['route' => 'features.booking', 'icon' => 'calendar-check', 'title' => 'Online booking', 'body' => 'Open time slots, confirmations and Mobile Money deposits.'],
        'pricing' => ['route' => 'pricing', 'icon' => 'wallet', 'title' => 'Pricing', 'body' => 'Plans in RWF, paid with Mobile Money. Start free.'],
    ];
    unset($pages[$current]);
@endphp
<section class="border-t border-border py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-[22px] font-semibold tracking-tight text-foreground" data-reveal>One studio, everything connected</h2>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach($pages as $page)
                <a href="{{ route($page['route']) }}" class="card card-hover group flex flex-col rounded-2xl p-6" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                    <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon :name="$page['icon']" /></span>
                    <h3 class="mt-4 text-[16px] font-semibold tracking-tight text-foreground">{{ $page['title'] }}</h3>
                    <p class="mt-1.5 flex-1 text-[14px] leading-relaxed text-muted-foreground">{{ $page['body'] }}</p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-[13.5px] font-medium text-foreground">
                        Learn more <x-icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
