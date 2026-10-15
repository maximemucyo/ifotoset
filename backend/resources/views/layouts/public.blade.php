<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-pt-20">
<head>
    @include('layouts.partials.head', ['defaultMode' => $defaultTheme ?? 'system'])
    <meta name="referrer" content="no-referrer-when-downgrade">
    @include('layouts.partials.seo')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="flex min-h-full flex-col bg-background text-foreground antialiased">
    <x-ui.skip-link />
    <x-admin.impersonation-banner />

    @if(!($hideNav ?? false))
        <header class="sticky top-0 z-40 w-full border-b border-border/70 glass">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <x-brand.logo :href="url('/')" />
                <nav class="flex items-center gap-1.5">
                    <x-ui.theme-toggle />
                    @auth
                        <a href="{{ route('studio.dashboard') }}" class="btn btn-primary btn-sm">Open studio <x-icon name="arrow-right" /></a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Sign in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Start free</a>
                    @endauth
                </nav>
            </div>
        </header>
    @endif

    <main id="main" class="flex-1" tabindex="-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @hasSection('footer')
        @yield('footer')
    @elseif(!($hideFooter ?? false))
        <footer class="mt-auto border-t border-border">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-[13px] text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <x-brand.logo :href="url('/')" size="sm" :wordmark="false" />
                    <p>&copy; {{ date('Y') }} ifotoset. Crafted for photographers.</p>
                </div>
                <div class="flex items-center gap-5">
                    <a href="{{ url('/') }}" class="transition hover:text-foreground">Home</a>
                    <a href="{{ url('/') }}#pricing" class="transition hover:text-foreground">Pricing</a>
                    <a href="{{ route('legal.terms') }}" class="transition hover:text-foreground">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="transition hover:text-foreground">Privacy</a>
                    <a href="{{ route('login') }}" class="transition hover:text-foreground">Sign in</a>
                </div>
            </div>
        </footer>
    @endif

    @include('layouts.partials.toaster')
    @stack('modals')
    @stack('scripts')
</body>
</html>
