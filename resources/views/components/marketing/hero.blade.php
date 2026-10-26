@props(['eyebrow', 'description'])
<section class="relative isolate overflow-hidden">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[620px] bg-grid mask-radial opacity-60 dark:opacity-40"></div>
    <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-[-280px] -z-10 h-[520px] w-[min(1000px,140vw)] -translate-x-1/2 rounded-full bg-brand/20 blur-[120px] dark:bg-brand/12"></div>

    <div class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 sm:pt-20 lg:px-8 lg:pt-24">
        <div class="mx-auto max-w-3xl text-center">
            <p class="inline-flex items-center gap-2 text-[13px] font-medium text-brand">
                <span class="h-px w-5 bg-brand/50" aria-hidden="true"></span>{{ $eyebrow }}
            </p>
            <h1 class="mt-4 text-[40px] font-semibold leading-[1.04] tracking-[-0.035em] text-foreground sm:text-[56px] lg:text-[64px]">
                {{ $slot }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">{{ $description }}</p>

            <div class="mt-9 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                @auth
                    <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-xl">Open your studio <x-icon name="arrow-right" /></a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">Start free <x-icon name="arrow-right" /></a>
                @endauth
                <a href="{{ route('pricing') }}" class="btn btn-outline btn-xl">See pricing</a>
            </div>
            <p class="mt-5 text-[13px] text-muted-foreground">Free plan, no card required.</p>
        </div>

        @isset($media)
            <div class="relative mx-auto mt-14 max-w-6xl sm:mt-16">
                <div aria-hidden="true" class="absolute -inset-x-6 -bottom-10 top-10 -z-10 rounded-[2rem] bg-gradient-to-b from-brand/10 via-transparent to-transparent blur-2xl"></div>
                {{ $media }}
            </div>
        @endisset
    </div>
</section>
