<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Studio - ifotoset' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-background text-foreground font-sans antialiased" x-data="{ sidebarOpen: false }">
    @php
        $flashMessage = session('success') ?? session('error') ?? session('warning') ?? session('info') ?? session('message');
        $flashType = session('success') ? 'success' : (session('error') ? 'error' : (session('warning') ? 'warning' : 'info'));
    @endphp

    <!-- Toast Flash Notifications -->
    <div x-data="toastNotification('{{ addslashes($flashMessage ?? '') }}', '{{ $flashType }}')"
         x-show="show"
         x-transition
         class="fixed bottom-5 right-5 z-50 max-w-sm w-full bg-card border border-border shadow-xl rounded-xl p-4 flex items-start gap-3"
         style="display: none;">
        <div class="w-2.5 h-2.5 mt-1 rounded-full shrink-0"
             :class="{
                 'bg-green-500': type === 'success',
                 'bg-destructive': type === 'error',
                 'bg-amber-500': type === 'warning',
                 'bg-primary': type === 'info'
             }"></div>
        <div class="flex-1 text-xs font-medium text-foreground leading-relaxed" x-text="message"></div>
        <button @click="show = false" class="text-muted-foreground hover:text-foreground text-sm leading-none">&times;</button>
    </div>

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-foreground/30 backdrop-blur-sm lg:hidden"
         style="display: none;"></div>

    <div class="flex h-full">
        <!-- Sidebar Navigation -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 z-50 flex w-64 flex-col bg-sidebar border-r border-sidebar-border transition-transform duration-300 ease-in-out lg:static lg:translate-x-0">
            <!-- Brand / Logo -->
            <div class="flex h-16 shrink-0 items-center justify-between px-6 border-b border-sidebar-border">
                <a href="{{ route('studio.dashboard') }}" class="flex items-center gap-2">
                    <span class="text-xl font-bold tracking-tight text-primary">ifotoset</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-primary/10 text-primary">Studio</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-sidebar-foreground/70 hover:text-sidebar-foreground">
                    &times;
                </button>
            </div>

            <!-- Admin Switcher Banner (Visible only for Superadmins) -->
            @if(auth()->user() && auth()->user()->isAdmin())
                <div class="px-4 pt-3">
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Admin Panel
                        </span>
                        <span>&rarr;</span>
                    </a>
                </div>
            @endif

            <!-- Nav Links -->
            <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
                @php
                    $navItems = [
                        ['label' => 'Dashboard', 'route' => 'studio.dashboard', 'icon' => 'home'],
                        ['label' => 'Galleries', 'route' => 'studio.galleries.index', 'icon' => 'images'],
                        ['label' => 'Bookings', 'route' => 'studio.bookings.index', 'icon' => 'calendar'],
                        ['label' => 'Availability', 'route' => 'studio.availability.index', 'icon' => 'clock'],
                        ['label' => 'Clients', 'route' => 'studio.clients.index', 'icon' => 'users'],
                        ['label' => 'Packages', 'route' => 'studio.packages.index', 'icon' => 'tag'],
                        ['label' => 'Analytics', 'route' => 'studio.analytics.index', 'icon' => 'bar-chart'],
                        ['label' => 'Settings', 'route' => 'studio.settings.index', 'icon' => 'settings'],
                        ['label' => 'Trash', 'route' => 'studio.trash.index', 'icon' => 'trash'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    @php 
                        $routePrefix = explode('.', $item['route'])[0] . '.' . explode('.', $item['route'])[1];
                        $isActive = request()->routeIs($item['route']) || request()->routeIs($routePrefix . '.*');
                    @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : url('/studio/' . strtolower($item['label'])) }}"
                       class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ $isActive ? 'bg-sidebar-accent text-sidebar-accent-foreground shadow-sm font-semibold' : 'text-sidebar-foreground hover:bg-sidebar-accent/50' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <!-- User footer info -->
            <div class="p-4 border-t border-sidebar-border flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="truncate text-xs">
                        <div class="font-semibold text-sidebar-foreground truncate">{{ auth()->user()->name ?? 'Photographer' }}</div>
                        <div class="text-muted-foreground truncate">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Logout" class="text-muted-foreground hover:text-foreground text-xs p-1">
                        &rarr;
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex flex-1 flex-col overflow-y-auto">
            <!-- Top Header -->
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-border bg-background/80 backdrop-blur-md px-4 sm:px-6 lg:px-8">
                <button @click="sidebarOpen = true" class="text-foreground lg:hidden p-2 rounded-lg hover:bg-card">
                    &#9776;
                </button>

                <div class="flex items-center gap-4 ml-auto">
                    @if(auth()->user() && auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Admin Mode &rarr;
                        </a>
                    @endif
                    @if(auth()->user() && auth()->user()->username)
                        <a href="{{ auth()->user()->public_url }}" target="_blank" class="text-xs font-medium text-muted-foreground hover:text-primary transition-colors flex items-center gap-1">
                            View Portfolio &nearr;
                        </a>
                    @endif
                </div>
            </header>

            <!-- Unverified Email Alert Banner -->
            @if(auth()->user() && !auth()->user()->hasVerifiedEmail())
                <div class="bg-amber-500/10 border-b border-amber-500/20 px-4 sm:px-6 lg:px-8 py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm text-amber-700 dark:text-amber-400">
                    <div class="flex items-center gap-2 font-medium">
                        <span>&#9888; Please verify your email address to unlock all studio features. Check your inbox for the verification link.</span>
                    </div>
                    <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-md font-medium text-xs transition-colors">
                            Resend Verification Link
                        </button>
                    </form>
                </div>
            @endif

            <!-- Content Area -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
