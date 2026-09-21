<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-google-tag />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer-when-downgrade">

    <title>{{ $title ?? config('app.name', 'ifotoset') }}</title>
    <meta name="description" content="{{ $description ?? 'Professional photography proofing, client galleries, and booking platform.' }}">

    <!-- OpenGraph / Social Meta Tags -->
    <meta property="og:title" content="{{ $title ?? config('app.name', 'ifotoset') }}">
    <meta property="og:description" content="{{ $description ?? 'Professional photography proofing and delivery platform.' }}">
    <meta property="og:type" content="website">
    @if(isset($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(($defaultTheme ?? '') === 'light')
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    @else
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    @endif
    @stack('styles')
</head>
<body class="h-full flex flex-col font-sans bg-background text-foreground antialiased selection:bg-primary/20 selection:text-primary">
    <x-admin.impersonation-banner />
    <!-- Flash Toast Notification -->
    <div x-data="toastNotification('{{ session('success') ?? session('message') }}', '{{ session('success') ? 'success' : (session('error') ? 'error' : 'info') }}')"
         x-show="show"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-[70] max-w-sm w-full bg-card border border-border shadow-xl rounded-xl p-4 flex items-start gap-3"
         style="display: none;">
        <div class="w-2 h-2 mt-2 rounded-full" :class="type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-destructive' : 'bg-primary')"></div>
        <div class="flex-1 text-sm font-medium text-foreground" x-text="message"></div>
        <button @click="show = false" class="text-muted-foreground hover:text-foreground text-xs">&times;</button>
    </div>

    @if(!($hideNav ?? false))
    <!-- Navigation Header -->
    <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-background/80 border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <img src="{{ asset('logo.png') }}" alt="ifotoset" class="w-7 h-7 object-contain">
                    <span class="text-xl font-bold tracking-tight text-primary">ifoto<span class="text-foreground">set</span></span>
                </a>
            </div>

            <nav class="flex items-center gap-4">
                @auth
                    <a href="{{ url('/studio/dashboard') }}" class="text-sm font-medium text-foreground hover:text-primary transition-colors">Studio</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-muted-foreground hover:text-foreground">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Sign in</a>
                    <a href="{{ route('register') }}" class="text-sm font-medium bg-primary text-primary-foreground px-4 py-2 rounded-lg shadow-sm hover:opacity-90 transition-opacity">Get Started</a>
                @endauth
            </nav>
        </div>
    </header>
    @endif

    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @hasSection('footer')
        @yield('footer')
    @elseif(!($hideFooter ?? false))
    <!-- Public Footer -->
    <footer class="border-t border-border bg-card py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
            <p>&copy; {{ date('Y') }} ifotoset. All rights reserved.</p>
            <div class="flex items-center gap-6">
                <a href="{{ url('/') }}" class="hover:text-foreground transition-colors">Home</a>
                <a href="{{ route('login') }}" class="hover:text-foreground transition-colors">Login</a>
                <a href="{{ route('register') }}" class="hover:text-foreground transition-colors">Register</a>
            </div>
        </div>
    </footer>
    @endif

    @stack('scripts')
</body>
</html>
