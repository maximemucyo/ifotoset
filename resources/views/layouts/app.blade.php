<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head')
    <title>{{ $title ?? 'Studio · ifotoset' }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-sidebar text-foreground antialiased">
    <x-ui.skip-link />
    <x-admin.impersonation-banner />
    <x-studio.team-context-banner />
    <x-studio.over-quota-banner />
    @php
        $studioOwner = auth()->check() ? request()->account() : null;
        $pendingBookings = $studioOwner
            ? \Illuminate\Support\Facades\Cache::remember('studio:pending-bookings:' . $studioOwner->id, 60, fn () => $studioOwner->bookings()->where('status', 'pending')->count())
            : 0;
    @endphp
    @include('layouts.partials.shell', [
        'context' => 'studio',
        'homeRoute' => 'studio.dashboard',
        'navGroups' => [
            ['label' => null, 'items' => [
                ['label' => 'Overview', 'route' => 'studio.dashboard', 'icon' => 'layout-dashboard'],
                ['label' => 'Galleries', 'route' => 'studio.galleries.index', 'icon' => 'images', 'match' => ['studio.galleries.*']],
                ['label' => 'Portfolio', 'route' => 'studio.portfolio.edit', 'icon' => 'palette', 'can' => 'studio.profile.manage'],
                ['label' => 'Analytics', 'route' => 'studio.analytics.index', 'icon' => 'chart-column', 'can' => 'studio.analytics.view'],
            ]],
            ['label' => 'Business', 'items' => [
                ['label' => 'Bookings', 'route' => 'studio.bookings.index', 'icon' => 'calendar-check', 'badge' => $pendingBookings ?: null],
                ['label' => 'Availability', 'route' => 'studio.availability.index', 'icon' => 'clock'],
                ['label' => 'Clients', 'route' => 'studio.clients.index', 'icon' => 'users'],
                ['label' => 'Packages', 'route' => 'studio.packages.index', 'icon' => 'package'],
            ]],
            ['label' => 'Account', 'items' => [
                ['label' => 'Billing & plans', 'route' => 'studio.billing.index', 'icon' => 'credit-card', 'can' => 'studio.billing'],
                ['label' => 'Team', 'route' => 'studio.team.index', 'icon' => 'user-plus', 'can' => 'studio.team.view'],
                ['label' => 'Settings', 'route' => 'studio.settings.index', 'icon' => 'settings'],
                ['label' => 'Trash', 'route' => 'studio.trash.index', 'icon' => 'trash-2'],
            ]],
        ],
    ])
    @include('layouts.partials.toaster')
    @stack('modals')
    @stack('scripts')
</body>
</html>
