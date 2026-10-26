@php($img = fn (string $n) => asset("images/marketing/hero-{$n}.webp"))

<section id="features" class="relative py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-heading eyebrow="One platform" description="Three tools that usually live in three different subscriptions, designed to work as one.">
            Everything after the shutter, <em class="font-serif font-normal italic tracking-normal">handled</em>.
        </x-landing.section-heading>

        <div class="mt-14 grid gap-5 lg:grid-cols-3">
            {{-- Galleries --}}
            <article class="card card-hover group flex flex-col overflow-hidden rounded-2xl" data-reveal>
                <div class="relative aspect-[5/4] overflow-hidden border-b sm:aspect-[4/3] lg:aspect-[5/4] xl:aspect-[4/3] border-border bg-muted/50">
                    <div aria-hidden="true" class="absolute inset-0 bg-dots opacity-60 mask-radial"></div>
                    <div class="absolute left-1/2 top-1/2 h-[68%] -translate-x-1/2 -translate-y-1/2">
                        <img src="{{ $img(2) }}" width="800" height="1000" alt="" loading="lazy" decoding="async" class="absolute left-1/2 top-1/2 h-full w-auto -translate-x-[95%] -translate-y-1/2 -rotate-[8deg] rounded-lg object-cover shadow-md ring-4 ring-card transition-transform duration-500 group-hover:-translate-x-[105%] group-hover:-rotate-[11deg]" style="aspect-ratio: 4/5">
                        <img src="{{ $img(13) }}" width="800" height="1000" alt="" loading="lazy" decoding="async" class="absolute left-1/2 top-1/2 h-full w-auto -translate-x-[5%] -translate-y-1/2 rotate-[8deg] rounded-lg object-cover shadow-md ring-4 ring-card transition-transform duration-500 group-hover:translate-x-[5%] group-hover:rotate-[11deg]" style="aspect-ratio: 4/5">
                        <img src="{{ $img(1) }}" width="1000" height="1250" alt="Couple on a bench at sunset" loading="lazy" decoding="async" class="relative h-full w-auto rounded-lg object-cover shadow-lg ring-4 ring-card" style="aspect-ratio: 4/5">
                    </div>
                    <span class="absolute bottom-4 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 whitespace-nowrap rounded-full border border-border bg-popover px-3 py-1 text-[12px] font-medium text-foreground shadow-sm">
                        <x-icon name="lock" class="size-3.5 text-success" /> Password protected
                    </span>
                </div>
                <div class="flex flex-1 flex-col p-6">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="images" /></span>
                        <h3 class="text-lg font-semibold tracking-tight">Client galleries</h3>
                    </div>
                    <p class="mt-3 text-[14.5px] leading-relaxed text-muted-foreground">Deliver full-resolution photos and films in a gallery that feels as considered as your work.</p>
                    <ul class="mt-5 space-y-2.5 text-[14px] text-foreground">
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Passwords, expiry and download controls</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Favourites and one-click full downloads</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Email invitations to your clients</li>
                    </ul>
                    <a href="{{ route('features.galleries') }}" class="mt-6 inline-flex items-center gap-1.5 self-start text-[13.5px] font-medium text-foreground transition-colors hover:text-brand">
                        Learn more<span class="sr-only"> about client galleries</span> <x-icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </a>
                </div>
            </article>

            {{-- Portfolio --}}
            <article class="card card-hover group flex flex-col overflow-hidden rounded-2xl" data-reveal style="--reveal-delay: 100ms">
                <div class="relative aspect-[5/4] overflow-hidden border-b sm:aspect-[4/3] lg:aspect-[5/4] xl:aspect-[4/3] border-border bg-muted/50 p-6 pb-0">
                    <div class="h-full overflow-hidden rounded-t-xl border border-b-0 border-border bg-card shadow-md transition-transform duration-500 group-hover:-translate-y-1.5">
                        <div class="flex items-center justify-between px-3.5 py-2.5 text-[10px]">
                            <span class="font-display text-[11px] font-semibold tracking-tight">Your Studio</span>
                            <span class="flex gap-2.5 text-muted-foreground"><span>Work</span><span>About</span><span class="text-foreground">Book</span></span>
                        </div>
                        <div class="relative mx-2.5 aspect-[16/8] overflow-hidden rounded-md">
                            <img src="{{ $img(7) }}" width="800" height="1000" alt="Photographer holding a vintage camera" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover object-[50%_40%]">
                            <div class="absolute inset-0 bg-black/30"></div>
                            <p class="absolute bottom-2.5 left-3 font-serif text-xl italic leading-none text-white">Stories, in light.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 p-2.5">
                            @foreach([3, 9, 12] as $n)
                                <img src="{{ $img($n) }}" width="800" height="800" alt="" loading="lazy" decoding="async" class="aspect-square w-full rounded-sm object-cover">
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-1 flex-col p-6">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="globe" /></span>
                        <h3 class="text-lg font-semibold tracking-tight">Portfolio website</h3>
                    </div>
                    <p class="mt-3 text-[14.5px] leading-relaxed text-muted-foreground">A fast, search-friendly site for your best work, with booking built in. No developer needed.</p>
                    <ul class="mt-5 space-y-2.5 text-[14px] text-foreground">
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Curated collections from your galleries</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Your own ifotoset subdomain (custom domains coming soon)</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> SEO metadata and social previews</li>
                    </ul>
                    <a href="{{ route('features.portfolio') }}" class="mt-6 inline-flex items-center gap-1.5 self-start text-[13.5px] font-medium text-foreground transition-colors hover:text-brand">
                        Learn more<span class="sr-only"> about portfolio websites</span> <x-icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </a>
                </div>
            </article>

            {{-- Studio manager --}}
            <article class="card card-hover group flex flex-col overflow-hidden rounded-2xl" data-reveal style="--reveal-delay: 200ms">
                <div class="relative aspect-[5/4] overflow-hidden border-b sm:aspect-[4/3] lg:aspect-[5/4] xl:aspect-[4/3] border-border bg-muted/50 p-6 pb-0">
                    <div class="h-full space-y-2 rounded-t-xl border border-b-0 border-border bg-card p-3.5 shadow-md transition-transform duration-500 group-hover:-translate-y-1.5">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-semibold text-foreground">Upcoming sessions</p>
                            <span class="text-[10px] text-muted-foreground">This week</span>
                        </div>
                        @foreach([
                            ['day' => 'Tue', 'date' => '14', 'title' => 'Engagement shoot', 'meta' => '10:00 · Outdoor', 'badge' => 'badge-success', 'status' => 'Paid'],
                            ['day' => 'Thu', 'date' => '16', 'title' => 'Family portraits', 'meta' => '15:30 · Studio', 'badge' => 'badge-warning', 'status' => 'Deposit'],
                            ['day' => 'Sat', 'date' => '18', 'title' => 'Wedding day', 'meta' => 'All day · Full package', 'badge' => 'badge-info', 'status' => 'Confirmed'],
                        ] as $row)
                            <div class="flex items-center gap-3 rounded-lg border border-border px-2.5 py-2">
                                <div class="flex w-8 shrink-0 flex-col items-center rounded-md bg-muted py-1 leading-none">
                                    <span class="text-[8.5px] uppercase text-muted-foreground">{{ $row['day'] }}</span>
                                    <span class="mt-0.5 font-display text-[13px] font-semibold tabular-nums">{{ $row['date'] }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[11.5px] font-medium text-foreground">{{ $row['title'] }}</p>
                                    <p class="truncate text-[10px] text-muted-foreground">{{ $row['meta'] }}</p>
                                </div>
                                <span class="badge {{ $row['badge'] }} text-[10px]">{{ $row['status'] }}</span>
                            </div>
                        @endforeach
                        <div class="flex items-center gap-2.5 rounded-lg bg-muted/70 px-2.5 py-2">
                            <span class="text-[10.5px] text-muted-foreground">Deposits collected</span>
                            <span class="progress h-1 flex-1"><span style="width: 66%"></span></span>
                            <span class="text-[10.5px] font-medium tabular-nums">2 of 3</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-1 flex-col p-6">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="layout-dashboard" /></span>
                        <h3 class="text-lg font-semibold tracking-tight">Studio manager</h3>
                    </div>
                    <p class="mt-3 text-[14.5px] leading-relaxed text-muted-foreground">Bookings, clients, packages and payments in one place, so nothing slips through the cracks.</p>
                    <ul class="mt-5 space-y-2.5 text-[14px] text-foreground">
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Availability calendar and online booking</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Client records and session history</li>
                        <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 text-brand" /> Packages with Mobile Money checkout</li>
                    </ul>
                    <a href="{{ route('features.booking') }}" class="mt-6 inline-flex items-center gap-1.5 self-start text-[13.5px] font-medium text-foreground transition-colors hover:text-brand">
                        Learn more<span class="sr-only"> about online booking</span> <x-icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </a>
                </div>
            </article>
        </div>
    </div>
</section>
