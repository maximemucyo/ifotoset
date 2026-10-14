<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head')
    <title>{{ $title ?? 'Sign in · ifotoset' }}</title>
    <meta name="robots" content="noindex, follow">
    <link rel="preload" as="image" href="{{ asset('images/marketing/auth.webp') }}" fetchpriority="high">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-background text-foreground antialiased">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">

        {{-- Form column --}}
        <div class="relative flex flex-col px-6 py-6 sm:px-10">
            <div class="flex items-center justify-between">
                <x-brand.logo :href="url('/')" />
                <x-ui.theme-toggle />
            </div>

            <div class="flex flex-1 items-center justify-center py-12">
                <div class="w-full max-w-[380px] animate-fade-up">
                    @isset($icon)
                        <div class="mb-6 flex size-11 items-center justify-center rounded-xl border border-border bg-card shadow-sm">
                            <x-icon :name="$icon" class="size-5 text-foreground" />
                        </div>
                    @endisset
                    <h1 class="font-display text-[28px] font-semibold leading-tight tracking-tight text-foreground">
                        {{ $heading ?? 'Welcome back' }}
                    </h1>
                    @isset($subheading)
                        <p class="mt-2 text-[15px] text-muted-foreground">{{ $subheading }}</p>
                    @endisset

                    <div class="mt-8 space-y-4">
                        @if ($errors->any() && $errors->count() > 1)
                            <div class="alert alert-danger">
                                <x-icon name="alert-circle" />
                                <ul class="space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('status') && session('status') !== 'verification-link-sent')
                            <div class="alert alert-success"><x-icon name="circle-check" /><span>{{ session('status') }}</span></div>
                        @endif

                        @if (session('success') || request('verified') == '1')
                            <div class="alert alert-success"><x-icon name="circle-check" /><span>{{ session('success') ?? 'Your email address has been verified. Sign in to continue.' }}</span></div>
                        @endif

                        @if (session('error') || request('verified') == '0')
                            <div class="alert alert-danger"><x-icon name="alert-circle" /><span>{{ session('error') ?? 'The verification link is invalid or has expired. Sign in to request a new link.' }}</span></div>
                        @endif
                    </div>

                    <div class="mt-2">
                        {{ $slot ?? '' }}
                        @yield('content')
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <p>&copy; {{ date('Y') }} ifotoset</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}#pricing" class="transition hover:text-foreground">Pricing</a>
                    <a href="{{ url('/') }}#faq" class="transition hover:text-foreground">Help</a>
                </div>
            </div>
        </div>

        {{-- Showcase column --}}
        <div class="relative hidden p-3 lg:block">
            <div class="noise relative h-full overflow-hidden rounded-[22px] bg-zinc-950">
                <img src="{{ asset('images/marketing/auth.webp') }}" alt="" class="absolute inset-0 size-full object-cover opacity-90" decoding="async" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-black/10"></div>

                {{-- Floating UI hints --}}
                <div class="absolute right-8 top-8 flex items-center gap-2 rounded-full border border-white/15 bg-black/30 px-3 py-1.5 text-xs font-medium text-white/90 backdrop-blur-md">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>
                    Gallery delivered · 412 photos
                </div>

                <div class="absolute inset-x-8 bottom-8 xl:inset-x-12 xl:bottom-12">
                    <p class="eyebrow !text-white/60">The studio platform for photographers</p>
                    <h2 class="mt-4 max-w-lg font-serif text-[38px] leading-[1.08] tracking-tight text-white xl:text-[46px]">
                        Deliver galleries your clients will <em class="italic text-[#ffb59c]">remember</em>.
                    </h2>
                    <ul class="mt-8 grid max-w-lg gap-3 text-sm text-white/80 sm:grid-cols-2">
                        <li class="flex items-center gap-2.5"><x-icon name="images" class="size-4 text-white/60" /> Beautiful client galleries</li>
                        <li class="flex items-center gap-2.5"><x-icon name="calendar-check" class="size-4 text-white/60" /> Bookings &amp; availability</li>
                        <li class="flex items-center gap-2.5"><x-icon name="smartphone" class="size-4 text-white/60" /> Mobile money payments</li>
                        <li class="flex items-center gap-2.5"><x-icon name="lock" class="size-4 text-white/60" /> Private, PIN-protected delivery</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.partials.toaster')
    @stack('scripts')
</body>
</html>
