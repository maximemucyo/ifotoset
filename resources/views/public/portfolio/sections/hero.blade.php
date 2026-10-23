{{-- Portfolio hero, one layout per template. Vars from public/photographer --}}
@php
    $headline = $portfolio->heroHeadline($photographer);
    $heroText = $portfolio->heroText($photographer);
    $role = $portfolio->roleLabel();
@endphp

@switch($template)
    @case('minimal')
        <section class="relative">
            <div class="mx-auto max-w-4xl px-4 pt-20 pb-14 text-center sm:px-6 sm:pt-28 sm:pb-20 lg:px-8">
                <p class="animate-fade-up text-[11px] font-medium uppercase tracking-[0.32em] text-muted-foreground">{{ $role }}@if($photographer->location) · {{ $photographer->location }}@endif</p>
                <h1 class="pf-heading mt-6 animate-fade-up text-5xl leading-[1.02] text-foreground sm:text-6xl lg:text-7xl" style="animation-delay: .08s">{{ $headline }}</h1>
                @if($heroText)
                    <p class="mx-auto mt-6 max-w-xl animate-fade-up text-base leading-relaxed text-muted-foreground sm:text-lg" style="animation-delay: .16s">{{ $heroText }}</p>
                @endif
                <div class="mt-9 animate-fade-up" style="animation-delay: .24s">
                    @include('public.portfolio.partials.hero-actions', ['center' => true])
                </div>
            </div>
            @if($heroImage)
                <div class="mx-auto max-w-6xl animate-fade-in px-4 pb-6 sm:px-6 lg:px-8" style="animation-delay: .2s">
                    <div class="overflow-hidden rounded-2xl bg-muted">
                        <img src="{{ $heroImage['src'] }}" @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="(min-width: 1152px) 1152px, 100vw" @endif alt="Work by {{ $photographer->name }}" fetchpriority="high" decoding="async" class="aspect-[16/9] size-full object-cover sm:aspect-[21/9]">
                    </div>
                </div>
            @endif
        </section>
        @break

    @case('bold')
        <section class="relative isolate flex min-h-[78svh] items-end overflow-hidden {{ $heroImage ? 'bg-black text-white' : 'bg-muted text-foreground' }}">
            @if($heroImage)
                <img src="{{ $heroImage['src'] }}" @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="100vw" @endif alt="" fetchpriority="high" decoding="async" class="absolute inset-0 -z-10 size-full animate-fade-in object-cover">
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/85 via-black/35 to-black/10"></div>
            @endif
            <div class="mx-auto w-full max-w-7xl px-4 pt-32 pb-14 sm:px-6 sm:pb-20 lg:px-8">
                <p class="animate-fade-up text-[11px] font-medium uppercase tracking-[0.32em] opacity-80">{{ $role }}</p>
                <h1 class="pf-heading mt-5 max-w-5xl animate-fade-up text-6xl leading-[0.95] sm:text-7xl lg:text-8xl" style="animation-delay: .08s">{{ $headline }}</h1>
                @if($heroText)
                    <p class="mt-6 max-w-xl animate-fade-up text-base leading-relaxed opacity-85 sm:text-lg" style="animation-delay: .16s">{{ $heroText }}</p>
                @endif
                <div class="mt-7 animate-fade-up" style="animation-delay: .2s">
                    @include('public.portfolio.partials.hero-meta', ['inverse' => (bool) $heroImage])
                </div>
                <div class="mt-9 animate-fade-up" style="animation-delay: .26s">
                    @include('public.portfolio.partials.hero-actions', ['inverse' => (bool) $heroImage])
                </div>
            </div>
        </section>
        @break

    @case('grid')
        @php($mosaic = ($heroImage ? collect([$heroImage])->merge($sideCovers) : $covers)->take(4)->values())
        <section class="relative">
            <div class="mx-auto grid max-w-[1600px] items-center gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-12 lg:px-8">
                <div class="{{ $mosaic->isNotEmpty() ? 'lg:col-span-5' : 'lg:col-span-8' }}">
                    <div class="flex animate-fade-up items-center gap-3">
                        <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="lg" />
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $role }}</p>
                            <p class="text-sm text-foreground">{{ '@' . $photographer->username }}</p>
                        </div>
                    </div>
                    <h1 class="pf-heading mt-7 animate-fade-up text-5xl leading-[1] text-foreground sm:text-6xl" style="animation-delay: .08s">{{ $headline }}</h1>
                    @if($heroText)
                        <p class="mt-5 max-w-lg animate-fade-up text-base leading-relaxed text-muted-foreground" style="animation-delay: .16s">{{ $heroText }}</p>
                    @endif
                    <div class="mt-6 animate-fade-up" style="animation-delay: .2s">
                        @include('public.portfolio.partials.hero-meta')
                    </div>
                    <div class="mt-8 animate-fade-up" style="animation-delay: .26s">
                        @include('public.portfolio.partials.hero-actions')
                    </div>
                </div>
                @if($mosaic->isNotEmpty())
                    <div class="grid animate-fade-in grid-cols-2 gap-1.5 lg:col-span-7" style="animation-delay: .15s">
                        @foreach($mosaic as $image)
                            <div class="overflow-hidden bg-muted {{ $mosaic->count() === 1 ? 'col-span-2' : '' }} {{ $mosaic->count() === 3 && $loop->first ? 'col-span-2' : '' }}">
                                <img src="{{ $image['src'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="(min-width: 1024px) 30vw, 50vw" @endif alt="{{ $loop->first ? 'Work by ' . $photographer->name : '' }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async"
                                     class="size-full object-cover {{ $mosaic->count() === 1 || ($mosaic->count() === 3 && $loop->first) ? 'aspect-[2/1]' : 'aspect-square' }}">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
        @break

    @default
        {{-- Editorial --}}
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-12 lg:gap-16 lg:px-8 lg:py-24">
                <div class="{{ $heroImage ? 'lg:col-span-6' : 'lg:col-span-8 lg:col-start-3 text-center' }}">
                    <div class="animate-fade-up flex items-center gap-3 {{ $heroImage ? '' : 'justify-center' }}">
                        <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="lg" class="ring-4 ring-background shadow-md" />
                        <div class="text-left">
                            <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $role }}</p>
                            <p class="text-sm text-foreground">{{ '@' . $photographer->username }}</p>
                        </div>
                    </div>

                    <h1 class="pf-heading mt-8 animate-fade-up text-5xl leading-[0.98] text-foreground sm:text-6xl lg:text-7xl" style="animation-delay: .08s">
                        {{ $headline }}
                    </h1>

                    @if($heroText)
                        <p class="mt-6 max-w-xl animate-fade-up text-base leading-relaxed text-muted-foreground sm:text-lg {{ $heroImage ? '' : 'mx-auto' }}" style="animation-delay: .16s">
                            {{ $heroText }}
                        </p>
                    @endif

                    <div class="mt-7 animate-fade-up" style="animation-delay: .22s">
                        @include('public.portfolio.partials.hero-meta', ['center' => !$heroImage])
                    </div>

                    <div class="mt-9 animate-fade-up" style="animation-delay: .28s">
                        @include('public.portfolio.partials.hero-actions', ['center' => !$heroImage])
                    </div>
                </div>

                @if($heroImage)
                    <div class="relative animate-fade-in lg:col-span-6" style="animation-delay: .15s">
                        <div class="grid grid-cols-6 gap-3">
                            <div class="{{ $sideCovers->isNotEmpty() ? 'col-span-4' : 'col-span-6' }} overflow-hidden rounded-2xl bg-muted">
                                <img src="{{ $heroImage['src'] }}" @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="(min-width: 1024px) 33vw, 66vw" @endif alt="Work by {{ $photographer->name }}" fetchpriority="high" decoding="async" class="aspect-[4/5] size-full object-cover">
                            </div>
                            @if($sideCovers->isNotEmpty())
                                <div class="col-span-2 flex flex-col gap-3 pt-10">
                                    @foreach($sideCovers as $c)
                                        <div class="overflow-hidden rounded-2xl bg-muted">
                                            <img src="{{ $c['src'] }}" @if($c['srcset']) srcset="{{ $c['srcset'] }}" sizes="(min-width: 1024px) 17vw, 33vw" @endif alt="" loading="lazy" decoding="async" class="aspect-[3/4] size-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
@endswitch
