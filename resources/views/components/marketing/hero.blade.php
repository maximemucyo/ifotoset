@props(['description'])
<section class="pb-4 pt-10 sm:pt-16 lg:pt-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <h1 class="headline-cover max-w-[16ch] text-foreground">
                {{ $slot }}
            </h1>
            <p class="mt-6 max-w-2xl text-[17px] leading-relaxed text-muted-foreground sm:text-lg">{{ $description }}</p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                @auth
                    <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-xl">Open your studio</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">Start free</a>
                @endauth
                <a href="{{ route('pricing') }}" class="btn btn-outline btn-xl">See pricing</a>
            </div>
            <p class="mt-4 text-[14px] text-muted-foreground">Free plan, no card required.</p>
        </div>

        @isset($media)
            <div class="mx-auto mt-12 max-w-6xl sm:mt-16">
                {{ $media }}
            </div>
        @endisset
    </div>
</section>
