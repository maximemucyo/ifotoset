@props(['overlay' => false])
@php
    $links = collect([
        ['route' => 'features.galleries', 'label' => 'Galleries'],
        ['route' => 'features.portfolio', 'label' => 'Portfolio'],
        ['route' => 'features.booking', 'label' => 'Booking'],
        ['route' => 'pricing', 'label' => 'Pricing'],
    ])->map(fn ($link) => $link + ['href' => route($link['route']), 'current' => request()->routeIs($link['route'])])->all();
@endphp
{{-- With overlay, the header sits transparent on the cover photo until the page scrolls --}}
<header
    x-data="{ scrolled: false, isOpen: false, overlay: @js((bool) $overlay) }"
    x-init="scrolled = window.scrollY > 8"
    @scroll.window="scrolled = window.scrollY > 8"
    @keydown.escape.window="isOpen = false"
    @class([
        'sticky top-0 z-50 w-full border-b transition-[background-color,border-color,box-shadow] duration-300',
        'on-photo border-transparent' => $overlay,
        'border-transparent' => !$overlay,
    ])
    {{-- Object syntax so Alpine also removes the server-rendered on-photo class --}}
    :class="{ 'glass border-border/70': scrolled || isOpen, 'on-photo': overlay && !(scrolled || isOpen), 'border-transparent': !(scrolled || isOpen) }"
>
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-brand.logo :href="url('/')" />

        {{-- Desktop links --}}
        <div class="hidden items-center gap-1 md:flex">
            @foreach($links as $link)
                <a href="{{ $link['href'] }}" @if($link['current']) aria-current="page" @endif @class([
                    'rounded-lg px-3 py-2 text-[14px] font-medium transition-colors hover:bg-muted hover:text-foreground',
                    'text-foreground' => $link['current'],
                    'text-muted-foreground' => !$link['current'],
                ])>{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="flex items-center gap-1">
            <div class="hidden items-center gap-1.5 md:flex">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Log out</button>
                    </form>
                    <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary">Open studio</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Start free</a>
                @endauth
            </div>

            {{-- On phones the main action stays one tap away, next to the menu --}}
            @auth
                <a href="{{ url('/studio/dashboard') }}" class="btn btn-primary h-10 px-3.5 md:hidden">Open studio</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary h-10 px-3.5 md:hidden">Start free</a>
            @endauth

            <button
                type="button"
                class="btn btn-ghost btn-icon size-11 text-foreground md:hidden"
                @click="isOpen = !isOpen"
                :aria-expanded="isOpen.toString()"
                aria-controls="landing-mobile-menu"
                aria-label="Menu"
            >
                <x-icon name="menu" class="size-5" x-show="!isOpen" />
                <x-icon name="x" class="size-5" x-show="isOpen" x-cloak />
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
        <div class="mx-auto max-w-7xl px-4 pb-5 pt-2 sm:px-6">
            @foreach($links as $link)
                <a href="{{ $link['href'] }}" @if($link['current']) aria-current="page" @endif @click="isOpen = false" class="flex min-h-12 items-center justify-between rounded-lg px-3 text-[16px] font-medium text-foreground transition-colors hover:bg-muted">
                    {{ $link['label'] }}
                    <x-icon name="chevron-right" class="size-4 text-subtle" />
                </a>
            @endforeach

            <div class="mt-3 border-t border-border pt-3">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-lg w-full">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @click="isOpen = false" class="btn btn-outline btn-lg w-full">Sign in</a>
                @endauth
            </div>
        </div>
    </div>
</header>
