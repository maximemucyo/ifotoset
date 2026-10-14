@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
])

@php
    $covers = $galleries->map(fn ($g) => $g->getCoverUrl('lg'))->filter()->values();
    $heroCover = $covers->first();
    $websiteHost = $photographer->website ? (parse_url($photographer->website, PHP_URL_HOST) ?? $photographer->website) : null;
    $hasPackages = $packages->isNotEmpty();
    $selectedPackage = old('package_id');
@endphp

@section('content')
<div x-data="{ pickPackage(id) { const s = document.querySelector('#package_id'); if (s) s.value = id; document.getElementById('book')?.scrollIntoView({ behavior: 'smooth' }); setTimeout(() => document.getElementById('client_name')?.focus({ preventScroll: true }), 600); } }">

    {{-- ===================== Header ===================== --}}
    <header class="sticky top-0 z-40 border-b border-border/60 glass">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ $photographer->public_url }}" class="flex min-w-0 items-center gap-2.5">
                <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="sm" />
                <span class="truncate font-serif text-xl tracking-normal text-foreground">{{ $photographer->name }}</span>
            </a>
            <nav class="flex items-center gap-1">
                <a href="#work" class="btn btn-ghost btn-sm hidden sm:inline-flex">Work</a>
                @if($hasPackages)
                    <a href="#packages" class="btn btn-ghost btn-sm hidden sm:inline-flex">Packages</a>
                @endif
                <x-ui.theme-toggle />
                @if($hasPackages)
                    <a href="#book" class="btn btn-primary btn-sm ml-1">Book a session</a>
                @endif
            </nav>
        </div>
    </header>

    {{-- ===================== Hero ===================== --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-12 lg:gap-16 lg:px-8 lg:py-24">
            <div class="{{ $heroCover ? 'lg:col-span-6' : 'lg:col-span-8 lg:col-start-3 text-center' }}">
                <div class="animate-fade-up flex items-center gap-3 {{ $heroCover ? '' : 'justify-center' }}">
                    <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="lg" class="ring-4 ring-background shadow-md" />
                    <div class="text-left">
                        <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Photographer</p>
                        <p class="text-sm text-foreground">{{ '@' . $photographer->username }}</p>
                    </div>
                </div>

                <h1 class="mt-8 animate-fade-up font-serif text-5xl leading-[0.98] tracking-normal text-foreground sm:text-6xl lg:text-7xl" style="animation-delay: .08s">
                    {{ $photographer->name }}
                </h1>

                @if($photographer->bio)
                    <p class="mt-6 max-w-xl animate-fade-up text-base leading-relaxed text-muted-foreground sm:text-lg {{ $heroCover ? '' : 'mx-auto' }}" style="animation-delay: .16s">
                        {{ $photographer->bio }}
                    </p>
                @endif

                <div class="mt-7 flex animate-fade-up flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-muted-foreground {{ $heroCover ? '' : 'justify-center' }}" style="animation-delay: .22s">
                    @if($photographer->location)
                        <span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5" /> {{ $photographer->location }}</span>
                    @endif
                    @if($websiteHost)
                        <a href="{{ $photographer->website }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 transition-colors hover:text-foreground">
                            <x-icon name="globe" class="size-3.5" /> {{ $websiteHost }}
                        </a>
                    @endif
                    <span class="inline-flex items-center gap-1.5"><x-icon name="images" class="size-3.5" /> {{ $galleries->count() }} {{ Str::plural('collection', $galleries->count()) }}</span>
                </div>

                <div class="mt-9 flex animate-fade-up flex-wrap gap-3 {{ $heroCover ? '' : 'justify-center' }}" style="animation-delay: .28s">
                    @if($hasPackages)
                        <a href="#book" class="btn btn-primary btn-lg">Request a booking <x-icon name="arrow-right" /></a>
                    @endif
                    @if($galleries->isNotEmpty())
                        <a href="#work" class="btn btn-outline btn-lg">View work</a>
                    @endif
                </div>
            </div>

            @if($heroCover)
                <div class="relative animate-fade-in lg:col-span-6" style="animation-delay: .15s">
                    <div class="grid grid-cols-6 gap-3">
                        <div class="{{ $covers->count() > 1 ? 'col-span-4' : 'col-span-6' }} overflow-hidden rounded-2xl bg-muted">
                            <img src="{{ $covers[0] }}" alt="Work by {{ $photographer->name }}" fetchpriority="high" decoding="async" class="aspect-[4/5] size-full object-cover">
                        </div>
                        @if($covers->count() > 1)
                            <div class="col-span-2 flex flex-col gap-3 pt-10">
                                @foreach($covers->slice(1, 2) as $c)
                                    <div class="overflow-hidden rounded-2xl bg-muted">
                                        <img src="{{ $c }}" alt="" decoding="async" class="aspect-[3/4] size-full object-cover">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ===================== Work ===================== --}}
    <section id="work" class="scroll-mt-16 border-t border-border/70">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Portfolio</p>
                    <h2 class="mt-3 font-serif text-4xl tracking-normal text-foreground sm:text-5xl">Selected work</h2>
                </div>
                @if($galleries->isNotEmpty())
                    <p class="text-sm text-muted-foreground">{{ $galleries->count() }} {{ Str::plural('collection', $galleries->count()) }}</p>
                @endif
            </div>

            @if($galleries->isEmpty())
                <x-ui.empty-state class="mt-10" icon="images" title="No public collections yet"
                                  description="{{ $photographer->name }} hasn't shared any galleries publicly. Check back soon." />
            @else
                <div class="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($galleries as $gallery)
                        @php($cover = $gallery->getCoverUrl('lg'))
                        <a href="{{ $gallery->public_url }}" class="group block" data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 80 }}ms">
                            <div class="relative aspect-[4/5] overflow-hidden rounded-xl bg-muted">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="{{ $gallery->title }}" loading="lazy" decoding="async"
                                         class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]">
                                    <div class="absolute inset-0 bg-black/0 transition-colors duration-500 group-hover:bg-black/15"></div>
                                @else
                                    <div class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-muted to-secondary p-8 text-center">
                                        <span class="font-serif text-6xl leading-none text-foreground/15">{{ mb_strtoupper(mb_substr($gallery->title, 0, 1)) }}</span>
                                        <span class="text-[11px] uppercase tracking-[0.24em] text-subtle">Cover coming soon</span>
                                    </div>
                                @endif
                                <span class="absolute bottom-4 right-4 inline-flex translate-y-1 items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-black opacity-0 shadow-sm backdrop-blur transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                                    View gallery <x-icon name="arrow-up-right" class="size-3.5" />
                                </span>
                            </div>
                            <div class="mt-4 flex items-baseline justify-between gap-4">
                                <h3 class="min-w-0 truncate font-serif text-2xl tracking-normal text-foreground">{{ $gallery->title }}</h3>
                                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ $gallery->photo_count }} {{ Str::plural('photo', $gallery->photo_count) }}</span>
                            </div>
                            @if($gallery->event_date)
                                <p class="mt-1 text-[11px] uppercase tracking-[0.2em] text-muted-foreground">{{ \Carbon\Carbon::parse($gallery->event_date)->format('F Y') }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if($hasPackages)
        {{-- ===================== Packages ===================== --}}
        <section id="packages" class="scroll-mt-16 border-t border-border/70 bg-muted/40">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Investment</p>
                    <h2 class="mt-3 font-serif text-4xl tracking-normal text-foreground sm:text-5xl">Packages</h2>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">Every session is tailored to you. Pick the package that fits, then send a request with your preferred date.</p>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($packages as $pkg)
                        <div class="card card-hover flex flex-col p-6 sm:p-7" data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 80 }}ms">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-serif text-2xl tracking-normal text-foreground">{{ $pkg->name }}</h3>
                                @if($pkg->duration_minutes)
                                    <span class="badge badge-neutral shrink-0"><x-icon name="clock" /> {{ $pkg->duration_minutes >= 60 && $pkg->duration_minutes % 60 === 0 ? ($pkg->duration_minutes / 60) . ' ' . Str::plural('hr', $pkg->duration_minutes / 60) : $pkg->duration_minutes . ' min' }}</span>
                                @endif
                            </div>

                            <div class="mt-5 flex items-baseline gap-1.5">
                                <span class="text-xs font-medium text-muted-foreground">{{ $pkg->currency }}</span>
                                <span class="font-display text-4xl font-semibold tracking-tight tabular-nums text-foreground">{{ number_format($pkg->price, 0) }}</span>
                            </div>

                            @if($pkg->description)
                                <p class="mt-4 text-[13px] leading-relaxed text-muted-foreground">{{ $pkg->description }}</p>
                            @endif

                            @if(!empty($pkg->deliverables))
                                <ul class="mt-5 space-y-2.5 border-t border-border pt-5 text-[13px] text-foreground">
                                    @foreach($pkg->deliverables as $item)
                                        <li class="flex items-start gap-2.5">
                                            <x-icon name="check" class="mt-0.5 size-4 text-success" />
                                            <span>{{ $item }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-auto pt-7">
                                <button type="button" @click="pickPackage('{{ $pkg->uuid }}')" class="btn btn-outline w-full">
                                    Book this package <x-icon name="arrow-right" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ===================== Booking request ===================== --}}
        <section id="book" class="scroll-mt-16 border-t border-border/70">
            <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-12 lg:px-8">
                <div class="lg:col-span-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Booking</p>
                    <h2 class="mt-3 font-serif text-4xl leading-[1.05] tracking-normal text-foreground sm:text-5xl">Let's make something <em class="italic">beautiful</em>.</h2>
                    <p class="mt-5 max-w-md text-sm leading-relaxed text-muted-foreground">
                        Share a few details about your session. {{ $photographer->name }} will review your request and confirm availability by email.
                    </p>
                    <ul class="mt-8 space-y-4 text-[13px] text-muted-foreground">
                        <li class="flex items-start gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-medium text-foreground">1</span><span class="pt-1">Choose a package and your preferred date.</span></li>
                        <li class="flex items-start gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-medium text-foreground">2</span><span class="pt-1">Your request is sent straight to the photographer.</span></li>
                        <li class="flex items-start gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-medium text-foreground">3</span><span class="pt-1">You'll get a confirmation once it's approved.</span></li>
                    </ul>
                </div>

                <div class="lg:col-span-7">
                    <form method="POST" action="{{ route('public.photographer.book', ['username' => $photographer->username]) }}" class="card p-6 sm:p-8">
                        @csrf

                        @if(session('success'))
                            <div class="alert alert-success mb-6"><x-icon name="circle-check" /> <span>{{ session('success') }}</span></div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger mb-6"><x-icon name="circle-alert" /> <span>{{ session('error') }}</span></div>
                        @endif

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input label="Full name" name="client_name" required autocomplete="name" placeholder="Jane Smith"
                                        :value="old('client_name')" :error="$errors->first('client_name')" />
                            <x-ui.input label="Email" type="email" name="client_email" required autocomplete="email" placeholder="jane@example.com"
                                        :value="old('client_email')" :error="$errors->first('client_email')" />
                            <x-ui.input label="Phone / WhatsApp" type="tel" name="client_phone" autocomplete="tel" placeholder="+250 788 000 000" hint="Optional"
                                        :value="old('client_phone')" :error="$errors->first('client_phone')" />
                            <x-ui.input label="Preferred date & time" type="datetime-local" name="starts_at" required
                                        :value="old('starts_at')" :error="$errors->first('starts_at')" />
                            <div class="sm:col-span-2">
                                <x-ui.select label="Package" name="package_id" id="package_id" required :error="$errors->first('package_id')">
                                    @foreach($packages as $pkg)
                                        <option value="{{ $pkg->uuid }}" @selected($selectedPackage === (string) $pkg->uuid)>{{ $pkg->name }} · {{ $pkg->currency }} {{ number_format($pkg->price, 0) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            <div class="sm:col-span-2">
                                <x-ui.textarea label="Tell us about your session" name="notes" id="notes" rows="4"
                                               placeholder="Location, occasion, the mood you're after, anything special…"
                                               :value="old('notes')" :error="$errors->first('notes')" />
                            </div>
                        </div>

                        <div class="mt-7 flex flex-col-reverse items-stretch gap-3 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted-foreground">No payment is taken now.</p>
                            <button type="submit" class="btn btn-primary btn-lg">Send request <x-icon name="send" /></button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection

@section('footer')
<footer class="mt-auto border-t border-border/70">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-10 text-xs text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
        <p>&copy; {{ date('Y') }} {{ $photographer->name }}</p>
        @if($photographer->isFree())
            <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'profile', 'utm_medium' => 'footer_logo', 'utm_campaign' => 'powered_by']) }}" class="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
                <span>Powered by</span> <x-brand.logo size="sm" />
            </a>
        @endif
    </div>
</footer>
@endsection
