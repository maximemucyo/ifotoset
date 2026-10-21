@php
    $img = fn (string $n) => asset("images/marketing/hero-{$n}.webp");
    $tabs = [
        'gallery' => [
            'icon' => 'images',
            'title' => 'Client gallery',
            'body' => config('services.google.photos_export')
                ? 'A distraction-free viewer with favourites, full-set downloads and Google Photos export. Your clients never see an ad or a login wall.'
                : 'A distraction-free viewer with favourites and full-set downloads. Your clients never see an ad or a login wall.',
        ],
        'portfolio' => [
            'icon' => 'globe',
            'title' => 'Portfolio website',
            'body' => 'Feature your best galleries on a site that loads fast on any phone, and let visitors book a session directly.',
        ],
        'studio' => [
            'icon' => 'layout-dashboard',
            'title' => 'Studio dashboard',
            'body' => 'See bookings, payments, storage and gallery views at a glance. Every client and session, one click away.',
        ],
    ];
@endphp

<section id="showcase" class="relative overflow-hidden border-y border-border bg-muted/40 py-24 sm:py-32"
    x-data="{
        tab: 'gallery',
        order: ['gallery', 'portfolio', 'studio'],
        progress: 0,
        paused: false,
        timer: null,
        start() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.timer = setInterval(() => {
                if (this.paused || document.hidden) return;
                this.progress += 100 / 7000;
                if (this.progress >= 1) this.next();
            }, 100);
        },
        next() { this.progress = 0; this.tab = this.order[(this.order.indexOf(this.tab) + 1) % this.order.length]; },
        select(t) { this.tab = t; this.progress = 0; },
    }"
    x-init="start()"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-heading eyebrow="Showcase" description="A closer look at what you and your clients will use every day.">
            Designed for the way <em class="font-serif font-normal italic tracking-normal">you</em> work.
        </x-landing.section-heading>

        <div class="mt-14 grid items-center gap-8 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-12">
            {{-- Tab list --}}
            <div class="grid grid-cols-3 gap-1.5 sm:gap-2 lg:flex lg:flex-col" role="tablist" aria-label="Product showcase" data-reveal>
                @foreach($tabs as $key => $t)
                    <button
                        type="button"
                        role="tab"
                        id="showcase-tab-{{ $key }}"
                        aria-controls="showcase-panel-{{ $key }}"
                        :aria-selected="(tab === '{{ $key }}').toString()"
                        @click="select('{{ $key }}')"
                        class="group relative min-w-0 overflow-hidden rounded-xl border px-2 py-3 text-center transition-all duration-300 sm:px-4 lg:px-5 lg:py-4 lg:text-left"
                        :class="tab === '{{ $key }}' ? 'border-border bg-card shadow-sm' : 'border-transparent hover:bg-card/60'"
                    >
                        <span class="flex flex-col items-center gap-2 sm:flex-row sm:justify-center sm:gap-2.5 lg:justify-start">
                            <span class="inline-flex size-8 items-center justify-center rounded-lg transition-colors"
                                :class="tab === '{{ $key }}' ? 'bg-brand/10 text-brand' : 'bg-muted text-muted-foreground'">
                                <x-icon :name="$t['icon']" />
                            </span>
                            <span class="text-[12.5px] font-semibold leading-tight tracking-tight sm:text-[15px]"
                                :class="tab === '{{ $key }}' ? 'text-foreground' : 'text-muted-foreground'">{{ $t['title'] }}</span>
                        </span>
                        <span class="hidden lg:block" x-show="tab === '{{ $key }}'" x-collapse>
                            <span class="block pt-3 text-[14px] leading-relaxed text-muted-foreground">{{ $t['body'] }}</span>
                        </span>
                        <span aria-hidden="true" class="absolute inset-x-0 bottom-0 hidden h-0.5 bg-border lg:block" x-show="tab === '{{ $key }}'">
                            <span class="block h-full origin-left bg-brand transition-transform duration-100 ease-linear" :style="`transform: scaleX(${tab === '{{ $key }}' ? progress : 0})`"></span>
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Mobile description --}}
            <div class="-mt-2 lg:hidden">
                @foreach($tabs as $key => $t)
                    <p class="text-[14.5px] leading-relaxed text-muted-foreground" x-show="tab === '{{ $key }}'" @if(!$loop->first) x-cloak @endif>{{ $t['body'] }}</p>
                @endforeach
            </div>

            {{-- Panels --}}
            <div class="relative" data-reveal style="--reveal-delay: 120ms">
                <div aria-hidden="true" class="absolute -inset-6 -z-10 rounded-[2rem] bg-brand/10 blur-3xl"></div>
                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xl dark:shadow-black/40">
                    <div class="flex items-center gap-1.5 border-b border-border bg-muted/60 px-4 py-2.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                        <span class="size-2.5 rounded-full bg-border-strong"></span>
                        <span class="ml-3 truncate text-[11.5px] text-muted-foreground"
                            x-text="{ gallery: 'yourstudio.ifotoset.com/golden-hour', portfolio: 'yourstudio.ifotoset.com', studio: 'ifotoset.com/studio' }[tab]">yourstudio.ifotoset.com/golden-hour</span>
                    </div>

                    <div class="grid aspect-[4/5] sm:aspect-[16/11] [&>*]:col-start-1 [&>*]:row-start-1">
                        {{-- Gallery viewer --}}
                        <div id="showcase-panel-gallery" role="tabpanel" aria-labelledby="showcase-tab-gallery"
                            x-show="tab === 'gallery'"
                            x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="opacity-0 scale-[0.985]" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            class="flex min-h-0 flex-col bg-black text-white">
                            <div class="flex items-center justify-between px-4 py-3 text-[12px] text-white/70">
                                <span class="inline-flex items-center gap-2"><x-icon name="arrow-left" class="size-3.5" /> Golden Hour</span>
                                <span class="tabular-nums">12 / 186</span>
                                <span class="flex items-center gap-3">
                                    <x-icon name="heart" class="size-4 fill-current text-brand" />
                                    <x-icon name="share-2" class="size-4" />
                                    <x-icon name="download" class="size-4" />
                                </span>
                            </div>
                            <div class="relative flex min-h-0 flex-1 items-center justify-center px-10 sm:px-16">
                                <span class="absolute left-3 inline-flex size-8 items-center justify-center rounded-full bg-white/10"><x-icon name="chevron-left" class="size-4" /></span>
                                <img src="{{ $img(1) }}" width="1000" height="1250" alt="Couple sitting on a bench at sunset" loading="lazy" decoding="async" class="max-h-full w-auto rounded-sm object-contain shadow-2xl">
                                <span class="absolute right-3 inline-flex size-8 items-center justify-center rounded-full bg-white/10"><x-icon name="chevron-right" class="size-4" /></span>
                            </div>
                            <div class="flex justify-center gap-1.5 overflow-hidden px-4 py-3">
                                @foreach([2, 5, 1, 13, 11, 10, 3] as $i => $n)
                                    <img src="{{ $img($n) }}" width="80" height="56" alt="" loading="lazy" decoding="async" class="h-10 w-14 shrink-0 rounded-sm object-cover {{ $n === 1 ? 'ring-2 ring-white' : 'opacity-50' }} {{ $i > 4 ? 'hidden sm:block' : '' }}">
                                @endforeach
                            </div>
                        </div>

                        {{-- Portfolio --}}
                        <div id="showcase-panel-portfolio" role="tabpanel" aria-labelledby="showcase-tab-portfolio"
                            x-show="tab === 'portfolio'" x-cloak
                            x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="opacity-0 scale-[0.985]" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            class="flex min-h-0 flex-col overflow-hidden bg-card">
                            <div class="relative h-[48%] shrink-0 overflow-hidden">
                                <img src="{{ $img(14) }}" width="1200" height="800" alt="Misty hills at sunrise" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/10 to-black/50"></div>
                                <div class="absolute inset-x-0 top-0 flex items-center justify-between px-5 py-3.5 text-[12px] text-white">
                                    <span class="font-display text-[13px] font-semibold tracking-tight">Your Studio</span>
                                    <span class="hidden gap-4 text-white/80 sm:flex"><span>Work</span><span>About</span><span>Contact</span></span>
                                    <span class="rounded-md bg-white px-2.5 py-1 text-[11px] font-medium text-black">Book a session</span>
                                </div>
                                <div class="absolute bottom-4 left-5 text-white sm:bottom-6 sm:left-7">
                                    <p class="text-[10px] uppercase tracking-[0.2em] text-white/70">Photography</p>
                                    <p class="mt-1 font-serif text-3xl italic leading-none sm:text-5xl">Light &amp; Story</p>
                                </div>
                            </div>
                            <div class="flex min-h-0 flex-1 flex-col p-4 sm:p-6">
                                <div class="flex items-baseline justify-between">
                                    <p class="text-[13px] font-semibold text-foreground">Selected work</p>
                                    <p class="text-[11px] text-muted-foreground">View all</p>
                                </div>
                                <div class="mt-3 grid min-h-0 flex-1 grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3">
                                    @foreach([['n' => 6, 't' => 'Travel'], ['n' => 2, 't' => 'Portraits'], ['n' => 4, 't' => 'Events']] as $i => $c)
                                        <div class="relative min-h-0 overflow-hidden rounded-lg {{ $i === 2 ? 'hidden sm:block' : '' }}">
                                            <img src="{{ $img($c['n']) }}" width="800" height="1000" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                            <span class="absolute bottom-2 left-2.5 text-[12px] font-medium text-white">{{ $c['t'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Studio dashboard --}}
                        <div id="showcase-panel-studio" role="tabpanel" aria-labelledby="showcase-tab-studio"
                            x-show="tab === 'studio'" x-cloak
                            x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="opacity-0 scale-[0.985]" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            class="flex min-h-0 bg-background">
                            <aside class="hidden w-40 shrink-0 flex-col gap-0.5 border-r border-border bg-sidebar p-3 sm:flex">
                                <div class="mb-3 flex items-center gap-2 px-1.5">
                                    <x-brand.logo size="sm" :wordmark="false" />
                                    <span class="text-[12px] font-semibold">Your Studio</span>
                                </div>
                                @foreach([['layout-dashboard', 'Dashboard', true], ['images', 'Galleries', false], ['calendar', 'Bookings', false], ['users', 'Clients', false], ['package', 'Packages', false], ['chart-column', 'Analytics', false]] as [$icon, $label, $active])
                                    <span class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[11.5px] {{ $active ? 'bg-sidebar-accent font-medium text-foreground shadow-xs ring-1 ring-sidebar-border' : 'text-sidebar-foreground' }}">
                                        <x-icon :name="$icon" class="size-3.5 {{ $active ? 'text-brand' : 'opacity-70' }}" /> {{ $label }}
                                    </span>
                                @endforeach
                            </aside>
                            <div class="flex min-w-0 flex-1 flex-col gap-3 overflow-hidden p-4 sm:p-5">
                                <div>
                                    <p class="text-[15px] font-semibold tracking-tight text-foreground">Good morning</p>
                                    <p class="text-[11px] text-muted-foreground">Here's what's happening in your studio.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                                    @foreach([['Revenue', 'RWF 840k', 'wallet'], ['Bookings', '18', 'calendar-check'], ['Gallery views', '2.4k', 'eye'], ['Storage', '38%', 'hard-drive']] as $i => [$label, $value, $icon])
                                        <div class="rounded-lg border border-border bg-card p-2.5 {{ $i > 1 ? 'hidden lg:block' : '' }}">
                                            <p class="flex items-center gap-1.5 text-[10px] text-muted-foreground"><x-icon :name="$icon" class="size-3" /> {{ $label }}</p>
                                            <p class="mt-1 font-display text-[15px] font-semibold tabular-nums tracking-tight">{{ $value }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex min-h-0 flex-1 flex-col rounded-lg border border-border bg-card p-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11.5px] font-semibold">Revenue</p>
                                        <span class="text-[10px] text-muted-foreground">Last 12 weeks</span>
                                    </div>
                                    <div class="mt-2 flex min-h-0 flex-1 items-end gap-1.5">
                                        @foreach([38, 52, 44, 61, 48, 70, 58, 76, 64, 82, 72, 92] as $i => $h)
                                            <span class="flex-1 rounded-t-[3px] {{ $i === 11 ? 'bg-brand' : 'bg-brand/25' }}" style="height: {{ $h }}%"></span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="rounded-lg border border-border bg-card">
                                    @foreach([['Wedding package', 'Mobile Money', 'badge-success', 'Paid'], ['Portrait session', 'Mobile Money', 'badge-warning', 'Pending']] as [$what, $how, $badge, $status])
                                        <div class="flex items-center gap-2.5 px-3 py-2 {{ $loop->first ? '' : 'border-t border-border' }}">
                                            <span class="inline-flex size-6 items-center justify-center rounded-md bg-muted"><x-icon name="smartphone" class="size-3 text-muted-foreground" /></span>
                                            <span class="min-w-0 flex-1 truncate text-[11.5px] font-medium">{{ $what }}</span>
                                            <span class="hidden text-[10.5px] text-muted-foreground sm:inline">{{ $how }}</span>
                                            <span class="badge {{ $badge }} text-[10px]">{{ $status }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
