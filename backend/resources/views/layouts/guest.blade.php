<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Sign In - ifotoset' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-background text-foreground font-sans antialiased">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
            <span class="text-3xl font-bold tracking-tight text-primary">ifotoset</span>
        </a>
        <h2 class="mt-4 text-2xl font-bold tracking-tight text-foreground">
            {{ $heading ?? 'Welcome back' }}
        </h2>
        @if(isset($subheading))
            <p class="mt-2 text-sm text-muted-foreground">
                {{ $subheading }}
            </p>
        @endif
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-card py-8 px-6 shadow-xl shadow-foreground/5 border border-border sm:rounded-2xl sm:px-10">
            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-destructive/10 border border-destructive/20 p-4 text-sm text-destructive">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-6 rounded-lg bg-green-500/10 border border-green-500/20 p-4 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('success') || request('verified') == '1')
                <div class="mb-6 rounded-lg bg-green-500/10 border border-green-500/20 p-4 text-sm text-green-700 dark:text-green-300 font-medium">
                    {{ session('success') ?? 'Your email address has been verified successfully! Please sign in to continue.' }}
                </div>
            @endif

            @if (session('error') || request('verified') == '0')
                <div class="mb-6 rounded-lg bg-destructive/10 border border-destructive/20 p-4 text-sm text-destructive font-medium">
                    {{ session('error') ?? 'The verification link is invalid or has expired. Please sign in to request a new link.' }}
                </div>
            @endif

            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </div>

    @stack('scripts')
</body>
</html>
