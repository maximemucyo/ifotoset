@php
    $columns = [
        'Product' => [
            ['Client galleries', route('features.galleries')],
            ['Portfolio website', route('features.portfolio')],
            ['Online booking', route('features.booking')],
            ['Pricing', route('pricing')],
        ],
        'Account' => auth()->check()
            ? [['Open studio', url('/studio/dashboard')]]
            : [['Start free', route('register')], ['Sign in', route('login')], ['Reset password', route('password.request')]],
    ];
@endphp

<footer class="border-t border-border bg-hill/40">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <div class="max-w-sm">
                <x-brand.logo :href="url('/')" />
                <p class="mt-4 text-[15px] leading-relaxed text-muted-foreground">
                    Client galleries, portfolio websites and bookings for photographers. Built in East Africa, for photographers everywhere.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-6">
                @foreach($columns as $heading => $links)
                    <div>
                        <p class="text-[14px] font-semibold text-foreground">{{ $heading }}</p>
                        <ul class="mt-1">
                            @foreach($links as [$label, $href])
                                <li><a href="{{ $href }}" class="inline-flex min-h-11 items-center text-[15px] text-muted-foreground transition-colors hover:text-foreground">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-x-6 border-t border-border pt-4 text-[14px] text-muted-foreground">
            <p class="py-3">&copy; {{ date('Y') }} ifotoset</p>
            <a href="{{ route('legal.terms') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-foreground">Terms</a>
            <a href="{{ route('legal.privacy') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-foreground">Privacy</a>
        </div>
    </div>
</footer>
