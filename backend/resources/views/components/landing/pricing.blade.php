@props(['plans' => collect()])
@php
    /*
     | Marketing copy per plan slug. Prices, storage and video allowances come from
     | the Plan records; ":storage" and ":video" are replaced with live values.
     */
    $copy = [
        'free' => [
            'tagline' => 'Learn the platform',
            'description' => 'Perfect for new photographers starting to establish their presence.',
            'features' => ['Portfolio website', 'Unlimited galleries', ':storage', ':video', 'Email support'],
            'cta' => 'Get started free',
        ],
        'basic' => [
            'tagline' => 'Start delivering professionally',
            'description' => 'For growing photographers who need more storage and video tools.',
            'features' => ['Portfolio website', 'Unlimited galleries', ':storage', ':video', 'Email support'],
            'cta' => 'Choose Basic',
        ],
        'pro' => [
            'tagline' => 'Run your photography business',
            'description' => 'For established photographers running a full-time business.',
            'features' => ['Everything in Basic', ':storage', ':video', 'Booking manager', 'Secure payments integration', 'Custom domain support', 'Priority support'],
            'cta' => 'Choose Professional',
        ],
        'business' => [
            'tagline' => 'Scale your studio',
            'description' => 'For agencies, studios, and teams scaling their operations.',
            'features' => ['Everything in Professional', ':storage', ':video', 'Multi-user team accounts', 'Dedicated setup & onboarding', 'Priority support'],
            'cta' => 'Choose Business',
        ],
    ];

    $formatStorage = function (?int $bytes): string {
        $gb = (int) round(($bytes ?? 0) / 1024 ** 3);
        return $gb >= 1000 ? rtrim(rtrim(number_format($gb / 1000, 1), '0'), '.') . ' TB' : $gb . ' GB';
    };
    $formatVideo = function (int $seconds): string {
        if ($seconds <= 0) {
            return 'Photo galleries only';
        }
        if ($seconds < 3600) {
            $m = (int) round($seconds / 60);
            return "Up to {$m} " . ($m === 1 ? 'minute' : 'minutes') . ' of hosted video';
        }
        $h = round($seconds / 3600, 1);
        $h = $h == (int) $h ? (int) $h : $h;
        return "Up to {$h} " . ($h == 1 ? 'hour' : 'hours') . ' of hosted video';
    };
    $money = fn ($amount, $currency) => trim(($currency ?: '') . ' ' . number_format((float) $amount, 0));

    // Whole months saved by paying yearly (e.g. annual = 10x monthly -> 2), or null when it isn't a clean number.
    $monthsFree = function ($plan): ?int {
        $m = (float) $plan->monthly_price;
        $a = (float) $plan->annual_price;
        if ($m <= 0 || $a <= 0 || $a >= $m * 12) {
            return null;
        }
        $months = 12 - $a / $m;
        return abs($months - round($months)) < 0.01 ? (int) round($months) : null;
    };

    $cards = $plans->map(function ($plan) use ($copy, $formatStorage, $formatVideo, $money, $monthsFree) {
        $c = $copy[$plan->slug] ?? [
            'tagline' => $plan->name,
            'description' => null,
            'features' => [':storage', ':video'],
            'cta' => 'Choose ' . $plan->name,
        ];
        $monthly = (float) $plan->monthly_price;
        $annual = (float) $plan->annual_price;
        $free = $monthly <= 0 && $annual <= 0;
        $hasAnnual = $annual > 0;
        $fullYear = $monthly * 12;
        $months = $monthsFree($plan);
        $saving = $hasAnnual && $fullYear > $annual ? (int) round((1 - $annual / $fullYear) * 100) : 0;
        $perMonth = $hasAnnual ? $annual / 12 : $monthly;
        $perMonthNote = '≈ ' . $money($perMonth, $plan->currency) . '/mo';

        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'free' => $free,
            'featured' => $plan->slug === 'pro',
            'tagline' => $c['tagline'],
            'description' => $c['description'],
            'cta' => $c['cta'],
            'currency' => $plan->currency,
            'monthly' => number_format($monthly, 0),
            'hasAnnual' => $hasAnnual,
            // Yearly view leads with the real annual total; plans without an annual price stay monthly.
            'yearly' => number_format($hasAnnual ? $annual : $monthly, 0),
            'yearlyUnit' => $hasAnnual ? '/year' : '/month',
            'yearlyCompare' => $saving > 0 ? $money($fullYear, $plan->currency) : null,
            'yearlyNote' => match (true) {
                $months !== null => $perMonthNote . ' · ' . $months . ' ' . ($months === 1 ? 'month' : 'months') . ' free',
                $saving > 0 => $perMonthNote . ' · Save ' . $saving . '%',
                $hasAnnual => 'Billed annually',
                default => 'Billed monthly',
            },
            'features' => array_map(fn ($f) => match ($f) {
                ':storage' => $formatStorage($plan->storage_limit) . ' optimized storage',
                ':video' => $formatVideo((int) $plan->video_limit_seconds),
                default => $f,
            }, $c['features']),
        ];
    })->values();

    $maxSaving = $plans->map(function ($plan) {
        $m = (float) $plan->monthly_price * 12;
        $a = (float) $plan->annual_price;
        return $m > 0 && $a > 0 ? (1 - $a / $m) * 100 : 0;
    })->max() ?? 0;
    $maxSaving = (int) round($maxSaving);

    // "Get 2 months free" reads better than a percentage when every paid plan offers the same whole months.
    $paidMonthsFree = $plans->filter(fn ($p) => (float) $p->annual_price > 0)->map($monthsFree)->unique()->values();
    $savingBadge = match (true) {
        $paidMonthsFree->count() === 1 && $paidMonthsFree->first() !== null
            => 'Get ' . $paidMonthsFree->first() . ' ' . ($paidMonthsFree->first() === 1 ? 'month' : 'months') . ' free yearly',
        $maxSaving > 0 => 'Save up to ' . $maxSaving . '% yearly',
        default => null,
    };

    $cols = match (true) {
        $cards->count() >= 4 => 'md:grid-cols-2 xl:grid-cols-4',
        $cards->count() === 3 => 'md:grid-cols-3',
        $cards->count() === 2 => 'md:grid-cols-2 max-w-4xl mx-auto',
        default => 'max-w-md mx-auto',
    };
@endphp

<section id="pricing" x-data="{ billingPeriod: 'yearly' }" class="relative py-24 sm:py-32">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[520px] bg-dots opacity-50 mask-fade-b"></div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-heading eyebrow="Pricing" description="Start free and upgrade when your studio grows. No setup fees, no long-term contracts.">
            Simple pricing. <em class="font-serif font-normal italic tracking-normal">No surprises.</em>
        </x-landing.section-heading>

        @if($cards->isEmpty())
            <div class="mx-auto mt-12 max-w-md text-center" data-reveal>
                <p class="text-muted-foreground">Plans are being updated. You can still start for free today.</p>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-6">Start free <x-icon name="arrow-right" /></a>
            </div>
        @else
            {{-- Billing toggle --}}
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" data-reveal>
                <div class="relative inline-grid grid-cols-2 rounded-full border border-border bg-muted/70 p-1" role="radiogroup" aria-label="Billing period">
                    <span aria-hidden="true" class="absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full bg-card shadow-sm ring-1 ring-border transition-transform duration-300 ease-out"
                        :class="billingPeriod === 'yearly' ? 'translate-x-full' : 'translate-x-0'"></span>
                    <button type="button" role="radio" :aria-checked="(billingPeriod === 'monthly').toString()" @click="billingPeriod = 'monthly'"
                        class="relative z-10 h-9 rounded-full px-5 text-[13.5px] font-medium transition-colors"
                        :class="billingPeriod === 'monthly' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'">Monthly</button>
                    <button type="button" role="radio" :aria-checked="(billingPeriod === 'yearly').toString()" @click="billingPeriod = 'yearly'"
                        class="relative z-10 h-9 rounded-full px-5 text-[13.5px] font-medium transition-colors"
                        :class="billingPeriod === 'yearly' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'">Yearly</button>
                </div>
                @if($savingBadge)
                    <span class="badge badge-brand rounded-full px-2.5 py-1 text-[12px]"><x-icon name="sparkles" /> {{ $savingBadge }}</span>
                @endif
            </div>

            <div class="mt-12 grid items-stretch gap-5 {{ $cols }}">
                @foreach($cards as $i => $card)
                    <div @class([
                            'relative flex flex-col rounded-2xl border p-7 transition-[border-color,box-shadow] duration-300',
                            'border-brand/50 bg-card shadow-xl shadow-brand/5 ring-1 ring-brand/40 dark:shadow-black/40' => $card['featured'],
                            'border-border bg-card shadow-xs hover:border-border-strong hover:shadow-md' => !$card['featured'],
                        ])
                        data-reveal style="--reveal-delay: {{ $i * 70 }}ms">
                        @if($card['featured'])
                            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-32 rounded-t-2xl bg-gradient-to-b from-brand/10 to-transparent"></div>
                            <span class="absolute -top-3 left-7 inline-flex items-center gap-1 rounded-full bg-brand px-2.5 py-1 text-[11px] font-semibold text-brand-foreground shadow-sm">
                                <x-icon name="star" class="size-3 fill-current" /> Most popular
                            </span>
                        @endif

                        <div class="relative">
                            <h3 class="text-lg font-semibold tracking-tight">{{ $card['name'] }}</h3>
                            <p class="mt-1 text-[13px] font-medium text-brand">{{ $card['tagline'] }}</p>
                            @if($card['description'])
                                <p class="mt-3 min-h-[44px] text-[13.5px] leading-relaxed text-muted-foreground">{{ $card['description'] }}</p>
                            @endif

                            <div class="mt-6">
                                {{-- Always rendered so every card's price block has the same height. --}}
                                <p class="h-5 text-[13px] tabular-nums text-muted-foreground line-through decoration-muted-foreground/60"
                                    @if($card['yearlyCompare']) :class="billingPeriod === 'yearly' ? 'visible' : 'invisible'" @else aria-hidden="true" @endif>
                                    {{ $card['yearlyCompare'] ?? '' }}
                                </p>
                                <div class="mt-1 flex items-baseline gap-1.5 whitespace-nowrap">
                                    <span class="text-[14px] font-medium text-muted-foreground">{{ $card['currency'] }}</span>
                                    @if($card['free'])
                                        <span class="font-display text-[34px] font-semibold leading-none tracking-[-0.03em] tabular-nums">{{ $card['monthly'] }}</span>
                                        <span class="text-[13px] text-muted-foreground">/month</span>
                                    @else
                                        <span class="font-display text-[34px] font-semibold leading-none tracking-[-0.03em] tabular-nums"
                                            x-text="billingPeriod === 'monthly' ? @js($card['monthly']) : @js($card['yearly'])">{{ $card['yearly'] }}</span>
                                        <span class="text-[13px] text-muted-foreground"
                                            x-text="billingPeriod === 'monthly' ? '/month' : @js($card['yearlyUnit'])">{{ $card['yearlyUnit'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-[12px] text-muted-foreground">
                                    @if($card['free'])
                                        Free forever
                                    @else
                                        <span x-text="billingPeriod === 'yearly' ? @js($card['yearlyNote']) : 'Billed monthly'">{{ $card['yearlyNote'] }}</span>
                                    @endif
                                </p>
                            </div>

                            <a href="{{ route('register') }}" @class([
                                'btn btn-lg mt-6 w-full',
                                'btn-brand' => $card['featured'],
                                'btn-outline' => !$card['featured'],
                            ])>{{ $card['cta'] }}</a>
                        </div>

                        <ul class="relative mt-7 space-y-3 border-t border-border pt-6">
                            @foreach($card['features'] as $feature)
                                <li class="flex items-start gap-2.5 text-[13.5px] leading-snug text-foreground">
                                    <span class="mt-px inline-flex size-4.5 shrink-0 items-center justify-center rounded-full {{ $card['featured'] ? 'bg-brand/15 text-brand' : 'bg-muted text-foreground' }}">
                                        <x-icon name="check" class="size-3" stroke-width="3" />
                                    </span>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <p class="mx-auto mt-10 max-w-2xl text-center text-[12.5px] leading-relaxed text-muted-foreground">
                Prices in {{ $plans->first()->currency }}. Large uploads are transcoded automatically. Storage and video allowances are subject to fair use.
            </p>
        @endif
    </div>
</section>
