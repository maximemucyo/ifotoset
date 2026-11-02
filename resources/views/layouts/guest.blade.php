<!DOCTYPE html>
{{-- Auth pages stay light, like the marketing site they are reached from; the studio follows the visitor's theme --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-force-theme="light">
<head>
    @include('layouts.partials.head', ['forceTheme' => 'light'])
    <title>{{ $title ?? 'Sign in · ifotoset' }}</title>
    <meta name="robots" content="noindex, follow">
    {{-- The photo is only shown from lg up; don't make phones download it --}}
    <link rel="preload" as="image" href="{{ asset('images/marketing/auth.webp') }}" media="(min-width: 1024px)" fetchpriority="high">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="marketing min-h-full bg-background text-foreground antialiased">
    <x-ui.skip-link />
    <div class="lg:grid lg:min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">

        {{-- Form column --}}
        <div class="flex min-h-dvh flex-col px-4 sm:px-10">
            <div class="flex h-16 items-center justify-between">
                <x-brand.logo :href="url('/')" />
                {{-- The other way in, so nobody has to scroll past the form to find it --}}
                @guest
                    @if(request()->routeIs('login'))
                        <a href="{{ route('register') }}" class="btn btn-ghost h-11 px-3 text-[15px] text-foreground">Start free</a>
                    @elseif(!request()->routeIs('auth.google.*', 'team.invitation.*'))
                        <a href="{{ route('login') }}" class="btn btn-ghost h-11 px-3 text-[15px] text-foreground">Sign in</a>
                    @endif
                @endguest
            </div>

            <main id="main" tabindex="-1" class="flex flex-1 justify-center pb-12 pt-6 outline-none sm:items-center sm:pt-12">
                <div class="w-full max-w-[400px]">
                    {{-- A page whose heading changes between steps provides its own --}}
                    @hasSection('heading')
                        @yield('heading')
                    @else
                        @isset($icon)
                            <x-icon :name="$icon" class="mb-5 size-6 text-brand-strong" />
                        @endisset
                        <h1 class="font-headline text-[32px] font-semibold leading-[1.05] tracking-[-0.02em] text-foreground [font-stretch:90%] sm:text-[36px]">
                            {{ $heading ?? 'Welcome back' }}
                        </h1>
                        @isset($subheading)
                            <p class="mt-3 text-[16px] leading-relaxed text-muted-foreground">{{ $subheading }}</p>
                        @endisset
                    @endif

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
            </main>

            <div class="flex flex-wrap items-center gap-x-5 border-t border-border text-[14px] text-muted-foreground">
                <p class="py-3">&copy; {{ date('Y') }} ifotoset</p>
                <a href="{{ route('pricing') }}" class="inline-flex min-h-11 items-center transition hover:text-foreground">Pricing</a>
                <a href="{{ route('pricing') }}#faq" class="inline-flex min-h-11 items-center transition hover:text-foreground">Help</a>
                <a href="{{ route('legal.privacy') }}" class="inline-flex min-h-11 items-center transition hover:text-foreground">Privacy</a>
            </div>
        </div>

        {{-- Photo column: stays in view while a long form scrolls --}}
        <div class="sticky top-0 hidden h-dvh p-3 lg:block">
            <div class="relative isolate flex h-full flex-col justify-end overflow-hidden rounded-2xl bg-kivu p-10 text-white xl:p-14">
                <img src="{{ asset('images/marketing/auth.webp') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" decoding="async" fetchpriority="high">
                <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                <p class="inline-flex w-fit items-center gap-1.5 rounded-full bg-black/30 px-3 py-1.5 text-[13px] text-white/90 backdrop-blur">
                    <x-icon name="lock" class="size-3.5" /> yourstudio.ifotoset.com/golden-light
                </p>
                <p class="mt-5 max-w-md font-headline text-[44px] font-bold leading-[0.98] tracking-[-0.03em] [font-stretch:85%] xl:text-[52px]">
                    Every gallery you send is part of your brand.
                </p>
                <p class="mt-4 max-w-sm text-[16px] leading-relaxed text-white/80">
                    Client galleries, a portfolio website and bookings with Mobile Money deposits, in one studio.
                </p>
            </div>
        </div>
    </div>

    @include('layouts.partials.toaster')
    @stack('scripts')
</body>
</html>
