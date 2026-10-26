@php
    $columns = [
        'Product' => [
            ['Client galleries', route('features.galleries')],
            ['Portfolio website', route('features.portfolio')],
            ['Online booking', route('features.booking')],
            ['Pricing', route('pricing')],
        ],
        'Explore' => [
            ['Features', url('/#features')],
            ['Showcase', url('/#showcase')],
            ['How it works', url('/#how-it-works')],
            ['FAQ', url('/#faq')],
        ],
        'Account' => auth()->check()
            ? [['Open studio', url('/studio/dashboard')]]
            : [['Start free', route('register')], ['Sign in', route('login')], ['Reset password', route('password.request')]],
    ];
@endphp

<footer class="border-t border-border bg-card/50">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <div class="max-w-sm">
                <x-brand.logo :href="url('/')" />
                <p class="mt-4 text-[14px] leading-relaxed text-muted-foreground">
                    Client galleries, portfolio websites and studio management for photographers. Built in East Africa, for photographers everywhere.
                </p>
                <div class="mt-6 flex items-center gap-2">
                    <x-ui.theme-toggle variant="segmented" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                @foreach($columns as $heading => $links)
                    <div>
                        <p class="text-[13px] font-semibold text-foreground">{{ $heading }}</p>
                        <ul class="mt-4 space-y-2.5">
                            @foreach($links as [$label, $href])
                                <li><a href="{{ $href }}" class="text-[13.5px] text-muted-foreground transition-colors hover:text-foreground">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-14 flex flex-col items-center justify-between gap-4 border-t border-border pt-8 text-[13px] text-muted-foreground sm:flex-row">
            <p>&copy; {{ date('Y') }} ifotoset. All rights reserved.
                <a href="{{ route('legal.terms') }}" class="ml-3 transition-colors hover:text-foreground">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="ml-3 transition-colors hover:text-foreground">Privacy</a>
            </p>
            <p class="inline-flex items-center gap-1.5"><x-icon name="camera" class="size-3.5" /> Made for photographers</p>
        </div>
    </div>

    {{-- Oversized wordmark --}}
    <div aria-hidden="true" class="pointer-events-none select-none overflow-hidden">
        <p class="mx-auto -mb-[0.24em] max-w-7xl px-4 text-center font-display text-[24vw] font-semibold leading-none tracking-[-0.06em] text-foreground/[0.035] sm:px-6 lg:px-8 lg:text-[220px]">ifotoset</p>
    </div>
</footer>
