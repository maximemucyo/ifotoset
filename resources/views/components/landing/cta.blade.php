<section class="border-t border-border py-16 sm:py-24">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
        <div class="max-w-2xl">
            <h2 class="headline">Send your first gallery this week.</h2>
            <p class="mt-4 text-[17px] leading-relaxed text-muted-foreground">
                Free to start, no card required. Upgrade when you need more storage or video.
            </p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            @auth
                <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-xl">Open your studio</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary btn-xl">Create your free studio</a>
                <a href="{{ route('login') }}" class="btn btn-outline btn-xl">Sign in</a>
            @endauth
        </div>
    </div>
</section>
