@props(['code', 'title', 'message', 'icon' => 'circle-alert'])
<section class="relative isolate flex min-h-[calc(100vh-4rem)] items-center justify-center overflow-hidden px-4 py-20 sm:px-6">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-grid mask-radial opacity-60 dark:opacity-40"></div>
    <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 -z-10 h-[420px] w-[min(760px,120vw)] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand/10 blur-[110px]"></div>

    <div class="relative mx-auto w-full max-w-xl text-center">
        <p aria-hidden="true" class="pointer-events-none select-none bg-gradient-to-b from-foreground/[0.14] to-foreground/0 bg-clip-text font-display text-[132px] font-semibold leading-none tracking-[-0.06em] text-transparent sm:text-[200px]">{{ $code }}</p>

        <div class="-mt-10 animate-fade-up sm:-mt-16">
            <span class="inline-flex size-12 items-center justify-center rounded-2xl border border-border bg-card text-brand shadow-sm">
                <x-icon :name="$icon" class="size-5" />
            </span>
            <p class="mt-6 text-[13px] font-medium text-muted-foreground">Error {{ $code }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-[40px] sm:leading-[1.1]">{{ $title }}</h1>
            <p class="mx-auto mt-4 max-w-md text-[15px] leading-relaxed text-muted-foreground">{{ $message }}</p>

            <div class="mt-8 flex flex-col items-stretch justify-center gap-2.5 sm:flex-row sm:items-center">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-10 text-[13px] text-muted-foreground">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</section>
