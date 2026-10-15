@php
    $img = fn (string $n) => asset("images/marketing/hero-{$n}.webp");
    $small = [
        ['icon' => 'package', 'title' => 'Packages', 'body' => 'Publish your services and prices once, reuse them for every booking.'],
        ['icon' => 'shield-check', 'title' => 'Private by default', 'body' => 'Password-protected galleries, expiry dates and download permissions.'],
        ['icon' => 'globe', 'title' => 'Your own domain', 'body' => 'Use your ifotoset subdomain or connect a custom domain on Professional.'],
        ['icon' => 'zap', 'title' => 'Fast everywhere', 'body' => 'Images are optimised automatically, so galleries load quickly on mobile data.'],
    ];
@endphp

<section class="relative py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-heading eyebrow="Features" description="The details that make a studio feel professional, from the first enquiry to the final download.">
            Built for the whole <em class="font-serif font-normal italic tracking-normal">business</em>, not just the photos.
        </x-landing.section-heading>

        <div class="mt-14 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-12">
            {{-- Mobile Money --}}
            <article class="card relative overflow-hidden rounded-2xl p-6 col-span-2 sm:p-8 lg:col-span-7" data-reveal>
                <div class="grid items-center gap-8 sm:grid-cols-[1fr_auto]">
                    <div>
                        <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="smartphone" /></span>
                        <h3 class="mt-4 text-xl font-semibold tracking-tight">Get paid with Mobile Money</h3>
                        <p class="mt-2 max-w-sm text-[14.5px] leading-relaxed text-muted-foreground">Clients pay deposits and packages straight from their phone through PawaPay. No card machine, no chasing transfers.</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="badge badge-neutral">MTN MoMo</span>
                            <span class="badge badge-neutral">Airtel Money</span>
                            <span class="badge badge-neutral">East Africa</span>
                        </div>
                    </div>
                    <div class="mx-auto w-56 rounded-[1.75rem] border border-border bg-background p-2 shadow-lg" aria-hidden="true">
                        <div class="rounded-[1.35rem] border border-border bg-card p-4">
                            <div class="mx-auto h-1 w-10 rounded-full bg-border"></div>
                            <p class="mt-4 text-center text-[11px] text-muted-foreground">Pay Your Studio</p>
                            <p class="mt-1 text-center font-display text-2xl font-semibold tabular-nums tracking-tight">RWF 50,000</p>
                            <p class="text-center text-[11px] text-muted-foreground">Wedding package · Deposit</p>
                            <div class="mt-4 space-y-1.5">
                                <div class="flex items-center gap-2 rounded-lg border border-brand/40 bg-brand/5 px-2.5 py-2 text-[11.5px] font-medium">
                                    <span class="size-3 rounded-full border-4 border-brand"></span> Mobile Money
                                </div>
                                <div class="flex items-center gap-2 rounded-lg border border-border px-2.5 py-2 text-[11.5px] text-muted-foreground">
                                    <span class="size-3 rounded-full border border-border-strong"></span> 07X XXX XXXX
                                </div>
                            </div>
                            <div class="mt-3 flex h-8 items-center justify-center rounded-lg bg-primary text-[11.5px] font-medium text-primary-foreground">Confirm on your phone</div>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Bookings --}}
            <article class="card relative overflow-hidden rounded-2xl p-6 col-span-2 sm:p-8 lg:col-span-5" data-reveal style="--reveal-delay: 80ms">
                <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="calendar-check" /></span>
                <h3 class="mt-4 text-xl font-semibold tracking-tight">Bookings &amp; availability</h3>
                <p class="mt-2 text-[14.5px] leading-relaxed text-muted-foreground">Set the days you work and let clients request a slot from your portfolio.</p>
                <div class="mt-6 grid grid-cols-7 gap-1.5 text-center" aria-hidden="true">
                    @foreach(['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d)
                        <span class="text-[10.5px] font-medium text-subtle">{{ $d }}</span>
                    @endforeach
                    @foreach(range(1, 21) as $day)
                        @php($state = in_array($day, [6, 13, 20]) ? 'booked' : (in_array($day, [3, 4, 7, 10, 14, 17, 19, 21]) ? 'off' : 'open'))
                        <span @class([
                            'flex h-9 items-center justify-center rounded-md text-[12px] tabular-nums',
                            'bg-brand-strong font-semibold text-brand-foreground' => $state === 'booked',
                            'text-subtle line-through decoration-subtle/50' => $state === 'off',
                            'border border-border bg-muted/50 text-foreground' => $state === 'open',
                        ])>{{ $day }}</span>
                    @endforeach
                </div>
            </article>

            {{-- Video --}}
            <article class="card group relative overflow-hidden rounded-2xl col-span-2 lg:col-span-4" data-reveal>
                <div class="relative aspect-[16/9] overflow-hidden">
                    <img src="{{ $img(4) }}" width="1200" height="800" alt="Concert crowd under stage lights" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-[1.04]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                    <span class="absolute left-1/2 top-1/2 inline-flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-black shadow-lg backdrop-blur transition-transform group-hover:scale-110"><x-icon name="play" class="ml-0.5 size-5 fill-current" /></span>
                    <div class="absolute inset-x-4 bottom-3 flex items-center gap-2 text-[11px] text-white/90" aria-hidden="true">
                        <span class="tabular-nums">1:12</span>
                        <span class="h-1 flex-1 overflow-hidden rounded-full bg-white/25"><span class="block h-full w-1/3 bg-white"></span></span>
                        <span class="tabular-nums">3:40</span>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="flex items-center gap-2 text-[17px] font-semibold tracking-tight"><x-icon name="video" class="size-4.5 text-brand" /> Video delivery</h3>
                    <p class="mt-2 text-[14px] leading-relaxed text-muted-foreground">Upload highlight films next to the photos. We transcode them for smooth playback on any device.</p>
                </div>
            </article>

            {{-- Analytics --}}
            <article class="card relative flex flex-col overflow-hidden rounded-2xl col-span-2 lg:col-span-4" data-reveal style="--reveal-delay: 80ms">
                <div class="flex aspect-[16/9] flex-col justify-end border-b border-border bg-muted/40 px-6 pb-0 pt-6" aria-hidden="true">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[13px] font-medium text-muted-foreground">Gallery views</span>
                        <span class="inline-flex items-center gap-0.5 text-[12px] font-medium text-success"><x-icon name="trending-up" class="size-3.5" /> Trending up</span>
                    </div>
                    <svg viewBox="0 0 300 90" preserveAspectRatio="none" class="mt-3 h-24 w-full text-brand" fill="none">
                        <defs>
                            <linearGradient id="lg-area" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="currentColor" stop-opacity=".25" />
                                <stop offset="1" stop-color="currentColor" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="M0 70 C 30 66, 45 52, 70 56 S 115 38, 140 44 S 185 22, 210 30 S 260 10, 300 6 L300 90 L0 90 Z" fill="url(#lg-area)" />
                        <path d="M0 70 C 30 66, 45 52, 70 56 S 115 38, 140 44 S 185 22, 210 30 S 260 10, 300 6" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>
                <div class="p-6">
                    <h3 class="flex items-center gap-2 text-[17px] font-semibold tracking-tight"><x-icon name="chart-column" class="size-4.5 text-brand" /> Analytics</h3>
                    <p class="mt-2 text-[14px] leading-relaxed text-muted-foreground">Know which galleries get viewed, downloaded and shared, and how your revenue is trending.</p>
                </div>
            </article>

            {{-- Clients --}}
            <article class="card relative flex flex-col overflow-hidden rounded-2xl col-span-2 lg:col-span-4" data-reveal style="--reveal-delay: 160ms">
                <div class="flex aspect-[16/9] flex-col justify-center gap-2 border-b border-border bg-muted/40 px-6" aria-hidden="true">
                    @foreach([['A', 'Wedding · 3 sessions', 'w-24'], ['K', 'Portraits · 1 session', 'w-16'], ['M', 'Events · 2 sessions', 'w-20']] as $i => [$initial, $meta, $w])
                        <div class="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2 shadow-xs {{ $i === 1 ? 'ml-4' : 'mr-4' }}">
                            <span class="avatar size-7 text-[11px]">{{ $initial }}</span>
                            <div class="min-w-0 flex-1">
                                <span class="block h-2 {{ $w }} rounded-full bg-border-strong"></span>
                                <span class="mt-1 block text-[10.5px] text-muted-foreground">{{ $meta }}</span>
                            </div>
                            <x-icon name="mail" class="size-3.5 text-subtle" />
                        </div>
                    @endforeach
                </div>
                <div class="p-6">
                    <h3 class="flex items-center gap-2 text-[17px] font-semibold tracking-tight"><x-icon name="users" class="size-4.5 text-brand" /> Client CRM</h3>
                    <p class="mt-2 text-[14px] leading-relaxed text-muted-foreground">Every contact, booking, gallery and payment linked together. Invite clients to their gallery by email.</p>
                </div>
            </article>

            {{-- Small features --}}
            @foreach($small as $i => $f)
                <article class="card card-hover rounded-2xl p-5 sm:p-6 lg:col-span-3" data-reveal style="--reveal-delay: {{ $i * 60 }}ms">
                    <span class="inline-flex size-9 items-center justify-center rounded-lg border border-border bg-muted/60 text-foreground"><x-icon :name="$f['icon']" /></span>
                    <h3 class="mt-4 text-[15.5px] font-semibold tracking-tight">{{ $f['title'] }}</h3>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-muted-foreground">{{ $f['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
