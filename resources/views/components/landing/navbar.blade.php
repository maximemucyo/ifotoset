@php
    $links = collect([
        ['route' => 'features.galleries', 'label' => 'Galleries'],
        ['route' => 'features.portfolio', 'label' => 'Portfolio'],
        ['route' => 'features.booking', 'label' => 'Booking'],
        ['route' => 'pricing', 'label' => 'Pricing'],
    ])->map(fn ($link) => $link + ['href' => route($link['route']), 'current' => request()->routeIs($link['route'])])->all();
@endphp
<header
    x-data="{ scrolled: false, isOpen: false }"
    x-init="scrolled = window.scrollY > 8"
    @scroll.window="scrolled = window.scrollY > 8"
    @keydown.escape.window="isOpen = false"
    class="sticky top-0 z-50 w-full transition-[background-color,border-color,box-shadow] duration-300"
    :class="(scrolled || isOpen) ? 'glass border-b border-border/70' : 'border-b border-transparent'"
>
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-brand.logo :href="url('/')" />

        {{-- Desktop links --}}
        <div class="hidden items-center gap-1 md:flex">
            @foreach($links as $link)
                <a href="{{ $link['href'] }}" @if($link['current']) aria-current="page" @endif @class([
                    'rounded-lg px-3 py-1.5 text-[13.5px] font-medium transition-colors hover:bg-muted hover:text-foreground',
                    'text-foreground' => $link['current'],
                    'text-muted-foreground' => !$link['current'],
                ])>{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="flex items-center gap-1.5">
            <x-ui.theme-toggle />

            <div class="hidden items-center gap-1.5 md:flex">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                    </form>
                    <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary btn-sm">Open studio <x-icon name="arrow-right" /></a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Start free <x-icon name="arrow-right" /></a>
                @endauth
            </div>

            <button
                type="button"
                class="btn btn-ghost btn-icon btn-sm md:hidden"
                @click="isOpen = !isOpen"
                :aria-expanded="isOpen.toString()"
                aria-controls="landing-mobile-menu"
                aria-label="Toggle navigation menu"
            >
                <x-icon name="menu" x-show="!isOpen" />
                <x-icon name="x" x-show="isOpen" x-cloak />
            </button>
        </div>
    </nav>

    {{-- Mobile menu --}}
    <div
        id="landing-mobile-menu"
        x-show="isOpen"
        x-collapse
        x-cloak
        class="border-t border-border/70 md:hidden"
    >
        <div class="mx-auto max-w-7xl space-y-1 px-4 pb-5 pt-3 sm:px-6">
            @foreach($links as $link)
                <a href="{{ $link['href'] }}" @if($link['current']) aria-current="page" @endif @click="isOpen = false" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-[15px] font-medium text-foreground transition-colors hover:bg-muted">
                    {{ $link['label'] }}
                    <x-icon name="chevron-right" class="size-4 text-subtle" />
                </a>
            @endforeach

            <div class="grid grid-cols-2 gap-2 pt-3">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline w-full">Log out</button>
                    </form>
                    <a href="{{ url('/studio/dashboard') }}" @click="isOpen = false" class="btn btn-primary">Open studio</a>
                @else
                    <a href="{{ route('login') }}" @click="isOpen = false" class="btn btn-outline">Sign in</a>
                    <a href="{{ route('register') }}" @click="isOpen = false" class="btn btn-primary">Start free</a>
                @endauth
            </div>
        </div>
    </div>
</header>
