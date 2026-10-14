@php($img = fn (string $n) => asset("images/marketing/hero-{$n}.webp"))
<section class="px-4 pb-24 sm:px-6 sm:pb-32 lg:px-8">
    <div class="noise relative mx-auto max-w-7xl overflow-hidden rounded-3xl border border-border bg-card" data-reveal>
        <div aria-hidden="true" class="absolute inset-0 bg-grid opacity-50 mask-radial"></div>
        <div aria-hidden="true" class="absolute -bottom-40 left-1/2 h-80 w-[min(900px,120%)] -translate-x-1/2 rounded-full bg-brand/20 blur-[100px]"></div>

        <div class="relative grid items-center gap-12 px-6 py-16 sm:px-12 sm:py-20 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:px-16">
            <div class="text-center lg:text-left">
                <h2 class="text-[34px] font-semibold leading-[1.05] tracking-[-0.035em] sm:text-5xl lg:text-[56px]">
                    Ready to deliver your <em class="font-serif font-normal italic tracking-normal text-brand">best work</em>?
                </h2>
                <p class="mx-auto mt-5 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-[17px] lg:mx-0">
                    Set up your studio in minutes. Free to start, no card required, and you can upgrade whenever you're ready.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                    @auth
                        <a href="{{ url('/studio/dashboard') }}" class="btn btn-brand btn-xl">Open your studio <x-icon name="arrow-right" /></a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-brand btn-xl">Create your free studio <x-icon name="arrow-right" /></a>
                        <a href="{{ route('login') }}" class="btn btn-outline btn-xl">Sign in</a>
                    @endauth
                </div>
            </div>

            <div class="relative mx-auto hidden h-80 w-full max-w-sm sm:block" aria-hidden="true">
                <img src="{{ $img(9) }}" width="800" height="1000" alt="" loading="lazy" decoding="async" class="absolute left-0 top-6 aspect-[4/5] w-44 -rotate-6 rounded-xl object-cover shadow-xl ring-4 ring-card">
                <img src="{{ $img(12) }}" width="800" height="1000" alt="" loading="lazy" decoding="async" class="absolute right-0 top-0 aspect-[4/5] w-44 rotate-6 rounded-xl object-cover shadow-xl ring-4 ring-card">
                <img src="{{ $img(15) }}" width="1200" height="800" alt="" loading="lazy" decoding="async" class="absolute bottom-0 left-1/2 aspect-[3/2] w-56 -translate-x-1/2 rounded-xl object-cover shadow-xl ring-4 ring-card">
            </div>
        </div>
    </div>
</section>
