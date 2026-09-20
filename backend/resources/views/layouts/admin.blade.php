<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-google-tag />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin - ifotoset' }}</title>

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
        <!-- Admin Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 z-50 flex w-64 flex-col bg-card border-r border-border transition-transform duration-300 ease-in-out lg:static lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center justify-between px-6 border-b border-border">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <span class="text-xl font-bold tracking-tight text-primary">ifotoset</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-destructive/10 text-destructive">Superadmin</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-muted-foreground hover:text-foreground">
                    &times;
                </button>
            </div>

            <!-- Studio Switcher Banner -->
            <div class="px-4 pt-3">
                <a href="{{ route('studio.dashboard') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-lg bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20 transition-colors">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        Studio Mode
                    </span>
                    <span>&rarr;</span>
                </a>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
                @php
                    $adminNav = [
                        ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
                        ['label' => 'Users', 'route' => 'admin.users.index'],
                        ['label' => 'Galleries', 'route' => 'admin.galleries.index'],
                        ['label' => 'Payments', 'route' => 'admin.payments.index'],
                        ['label' => 'Plans & Pricing', 'route' => 'admin.plans.index'],
                        ['label' => 'Analytics', 'route' => 'admin.analytics.index'],
                        ['label' => 'Processing Queue', 'route' => 'admin.jobs.index'],
                        ['label' => 'Moderation', 'route' => 'admin.moderation.index'],
                        ['label' => 'Support', 'route' => 'admin.support.index'],
                        ['label' => 'Settings', 'route' => 'admin.settings.index'],
                    ];
                @endphp

                @foreach($adminNav as $item)
                    @php 
                        $routePrefix = explode('.', $item['route'])[0] . '.' . explode('.', $item['route'])[1];
                        $isActive = request()->routeIs($item['route']) || request()->routeIs($routePrefix . '.*');
                    @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : url('/admin/' . strtolower($item['label'])) }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $isActive ? 'bg-primary text-primary-foreground shadow-sm font-semibold' : 'text-foreground hover:bg-secondary/50' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="p-4 border-t border-border flex items-center justify-between text-xs">
                <span class="text-muted-foreground truncate">{{ auth()->user()->email ?? 'admin' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-primary hover:underline font-semibold">Logout</button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex flex-1 flex-col overflow-y-auto">
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-border bg-background/80 backdrop-blur-md px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="text-foreground lg:hidden p-2 rounded-lg hover:bg-card">
                        &#9776;
                    </button>
                    <span class="text-sm font-bold text-foreground">Platform Administration</span>
                </div>

                <div class="flex items-center gap-4">
                    <a href="{{ route('studio.dashboard') }}" class="hidden sm:inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20 transition-colors">
                        Studio Mode &nearr;
                    </a>
                    <a href="{{ url('/') }}" target="_blank" class="text-xs text-muted-foreground hover:text-primary transition-colors">
                        Public Site &rarr;
                    </a>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
