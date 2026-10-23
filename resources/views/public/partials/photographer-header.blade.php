{{-- Sticky header shared by the portfolio, About, Contact and booking pages.
     Vars: $photographer, $hasPackages, $onPortfolio (bool, the home page), $current ('about'|'contact'|'book'|null) --}}
@php
    $headerSettings = $photographer->portfolio();
    $home = $photographer->public_url;
    $base = ($onPortfolio ?? false) ? '' : $home;
    $current ??= null;
    $navLinks = array_values(array_filter([
        $headerSettings->sectionVisible('work') ? ['label' => 'Work', 'href' => $base . '#work', 'key' => 'work'] : null,
        ['label' => 'About', 'href' => $home . '/about', 'key' => 'about'],
        ($hasPackages && $headerSettings->sectionVisible('services')) ? ['label' => 'Packages', 'href' => $base . '#packages', 'key' => 'packages'] : null,
        ['label' => 'Contact', 'href' => $home . '/contact', 'key' => 'contact'],
    ]));
@endphp
@include('public.partials.portfolio-theme')
<header class="sticky top-0 z-40 border-b border-border/60 glass" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ $home }}" class="flex min-w-0 items-center gap-2.5">
            @if($photographer->logo_url)
                <img src="{{ $photographer->logo_url }}" alt="{{ $photographer->name }}" class="h-8 w-auto max-w-[180px] object-contain sm:h-9" decoding="async">
            @else
                <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="sm" />
                <span class="pf-heading truncate text-xl text-foreground">{{ $photographer->name }}</span>
            @endif
        </a>
        <nav class="flex items-center gap-1" aria-label="Portfolio">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}" @if($current === $link['key']) aria-current="page" @endif
                   class="btn btn-ghost btn-sm hidden md:inline-flex {{ $current === $link['key'] ? 'text-foreground' : '' }}">{{ $link['label'] }}</a>
            @endforeach
            <x-ui.theme-toggle />
            @if($hasPackages)
                <a href="{{ route('subdomain.booking', ['username' => $photographer->username]) }}" class="btn btn-primary btn-sm ml-1"><span class="sm:hidden">Book</span><span class="hidden sm:inline">Book a session</span></a>
            @endif
            <button type="button" class="btn btn-ghost btn-sm md:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="portfolio-menu" aria-label="Menu">
                <x-icon name="menu" x-show="!open" />
                <x-icon name="x" x-show="open" x-cloak />
            </button>
        </nav>
    </div>
    <div id="portfolio-menu" x-show="open" x-collapse x-cloak class="border-t border-border/60 md:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col px-4 py-2 sm:px-6" aria-label="Portfolio (mobile)">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}" @click="open = false" class="rounded-lg px-2 py-2.5 text-sm text-foreground transition hover:bg-muted">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
