<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head')
    <title>{{ $title ?? 'Admin · ifotoset' }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-sidebar text-foreground antialiased">
    <x-ui.skip-link />
    @include('layouts.partials.shell', [
        'context' => 'admin',
        'homeRoute' => 'admin.dashboard',
        'navGroups' => [
            ['label' => null, 'items' => [
                ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
                ['label' => 'Analytics', 'route' => 'admin.analytics.index', 'icon' => 'chart-column'],
            ]],
            ['label' => 'Platform', 'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'match' => ['admin.users.*']],
                ['label' => 'Galleries', 'route' => 'admin.galleries.index', 'icon' => 'images', 'match' => ['admin.galleries.*']],
                ['label' => 'Moderation', 'route' => 'admin.moderation.index', 'icon' => 'shield-check'],
            ]],
            ['label' => 'Revenue', 'items' => [
                ['label' => 'Payments', 'route' => 'admin.payments.index', 'icon' => 'wallet'],
                ['label' => 'Plans & pricing', 'route' => 'admin.plans.index', 'icon' => 'tag'],
            ]],
            ['label' => 'System', 'items' => [
                ['label' => 'Processing queue', 'route' => 'admin.jobs.index', 'icon' => 'cpu'],
                ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings'],
            ]],
        ],
    ])
    @include('layouts.partials.toaster')
    @stack('modals')
    @stack('scripts')
</body>
</html>
