@props(['plans' => collect(), 'headingTag' => 'h2'])
@php
    /*
     | Marketing copy per plan slug. Prices, storage and video allowances come from
     | the Plan records; ":storage" and ":video" are replaced with live values.
     */
    $copy = [
        'free' => [
            'tagline' => 'Learn the platform',
            'description' => 'Perfect for new photographers starting to establish their presence.',
            'features' => ['Portfolio website on your subdomain', 'Online booking page', 'Unlimited galleries', ':storage', ':video', 'Email support'],
            'cta' => 'Get started free',
        ],
        'basic' => [
            'tagline' => 'Start delivering professionally',
            'description' => 'For growing photographers who need more storage and video tools.',
            'features' => ['Portfolio website on your subdomain', 'Online booking page', 'Unlimited galleries', ':storage', ':video', 'Email support'],
            'cta' => 'Choose Basic',
        ],
        'pro' => [
            'tagline' => 'Run your photography business',
            'description' => 'For established photographers running a full-time business.',
            'features' => ['Everything in Basic', ':storage', ':video', 'Mobile Money booking deposits', 'Priority support', 'Custom domain (coming soon)'],
            'cta' => 'Choose Professional',
        ],
        'business' => [
            'tagline' => 'Scale your studio',
            'description' => 'For agencies, studios, and teams scaling their operations.',
            'features' => ['Everything in Professional', ':storage', ':video', 'Multi-user team accounts', 'Dedicated setup & onboarding', 'Priority support'],
            'cta' => 'Choose Business',
        ],
    ];

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

    $cards = $plans->map(function ($plan) use ($copy, $money, $monthsFree) {
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
        $billedNote = $money($annual, $plan->currency) . ' billed yearly';

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
            // Yearly view leads with the rounded per-month equivalent; the annual total sits in the note below.
            'yearly' => number_format($perMonth, 0),
            'yearlyUnit' => '/month',
            'yearlyCompare' => $saving > 0 ? $money($monthly, $plan->currency) : null,
            'yearlyNote' => match (true) {
                $months !== null => $billedNote . ' · ' . $months . ' ' . ($months === 1 ? 'month' : 'months') . ' free',
                $saving > 0 => $billedNote . ' · Save ' . $saving . '%',
                $hasAnnual => 'Billed yearly',
                default => 'Billed monthly',
            },
            'features' => array_map(fn ($f) => match ($f) {
                ':storage' => $plan->storageLabel() . ' optimized storage',
                ':video' => $plan->videoLabel() ? 'Up to ' . $plan->videoLabel() . ' of hosted video' : 'Photo galleries only',
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

    // Phones show one plan at a time, starting on the featured one
    $startIndex = max(0, (int) $cards->search(fn ($c) => $c['featured']));

    $cols = match (true) {
        $cards->count() >= 4 => 'md:grid-cols-2 xl:grid-cols-4',
        $cards->count() === 3 => 'md:grid-cols-3',
        $cards->count() === 2 => 'md:grid-cols-2 max-w-4xl mx-auto',
        default => 'max-w-md mx-auto',
    };
@endphp

<section id="pricing" x-data="{ billingPeriod: 'yearly', plan: {{ $startIndex }} }" class="scroll-mt-16 border-t border-border py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <{{ $headingTag }} class="headline">Start free. Upgrade when the bookings come in.</{{ $headingTag }}>
            <p class="mt-4 text-[17px] leading-relaxed text-muted-foreground">No setup fees and no long-term contracts.</p>
        </div>

        @if($cards->isEmpty())
            <div class="mt-10 max-w-md">
                <p class="text-muted-foreground">Plans are being updated. You can still start for free today.</p>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-6">Start free</a>
            </div>
        @else
            {{-- Billing toggle --}}
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <div class="relative inline-grid grid-cols-2 rounded-full border border-border bg-muted p-1" role="radiogroup" aria-label="Billing period">
                    <span aria-hidden="true" class="absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full bg-card shadow-sm ring-1 ring-border transition-transform duration-300 ease-out"
                        :class="billingPeriod === 'yearly' ? 'translate-x-full' : 'translate-x-0'"></span>
                    <button type="button" role="radio" :aria-checked="(billingPeriod === 'monthly').toString()" @click="billingPeriod = 'monthly'"
                        class="relative z-10 h-10 rounded-full px-5 text-[15px] font-medium transition-colors"
                        :class="billingPeriod === 'monthly' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'">Monthly</button>
                    <button type="button" role="radio" :aria-checked="(billingPeriod === 'yearly').toString()" @click="billingPeriod = 'yearly'"
                        class="relative z-10 h-10 rounded-full px-5 text-[15px] font-medium transition-colors"
                        :class="billingPeriod === 'yearly' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'">Yearly</button>
                </div>
                @if($savingBadge)
                    <span class="text-[14px] font-medium text-brand-strong">{{ $savingBadge }}</span>
                @endif
            </div>

            {{-- Plan picker for phones; from md every plan is visible --}}
            @if($cards->count() > 1)
                <div class="mt-6 flex gap-1 rounded-xl bg-muted p-1 md:hidden" role="tablist" aria-label="Plans">
                    @foreach($cards as $i => $card)
                        <button type="button" role="tab" id="plan-tab-{{ $card['slug'] }}" aria-controls="plan-{{ $card['slug'] }}"
                            :aria-selected="(plan === {{ $i }}).toString()" @click="plan = {{ $i }}"
                            @class(['h-11 flex-auto whitespace-nowrap rounded-lg px-2 text-[14px] font-medium transition-colors', 'bg-card text-foreground shadow-sm' => $i === $startIndex, 'text-muted-foreground' => $i !== $startIndex])
                            {{-- Object syntax: Alpine then also removes the server-rendered classes --}}
                            :class="{ 'bg-card text-foreground shadow-sm': plan === {{ $i }}, 'text-muted-foreground': plan !== {{ $i }} }">{{ $card['name'] }}</button>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 grid gap-x-5 gap-y-5 md:mt-10 {{ $cols }}">
                @foreach($cards as $i => $card)
                    <div id="plan-{{ $card['slug'] }}" role="tabpanel" aria-labelledby="plan-tab-{{ $card['slug'] }}"
                        @class([
                            {{-- From md the cards share rows (subgrid), so names, prices and buttons line up --}}
                            'relative flex-col rounded-2xl border bg-card p-6 sm:p-7 md:row-span-6 md:grid md:grid-rows-subgrid md:gap-0',
                            'border-brand-strong ring-1 ring-brand-strong' => $card['featured'],
                            'border-border' => !$card['featured'],
                            'flex' => $i === $startIndex,
                            'hidden' => $i !== $startIndex,
                        ])
                        :class="{ 'flex': plan === {{ $i }}, 'hidden': plan !== {{ $i }} }">
                        @if($card['featured'])
                            <span class="absolute -top-3 left-6 whitespace-nowrap rounded-full bg-brand-strong px-2.5 py-1 text-[12px] font-semibold text-brand-foreground sm:left-7">Most popular</span>
                        @endif
                        <h3 class="text-[22px] font-semibold">{{ $card['name'] }}</h3>
                        <p class="mt-1 text-[14px] font-medium text-brand-strong">{{ $card['tagline'] }}</p>
                        {{-- Always rendered: each card needs the same six rows --}}
                        <p class="mt-3 text-[15px] leading-relaxed text-muted-foreground">{{ $card['description'] }}</p>

                        <div class="mt-6">
                            {{-- Always rendered so every card's price block has the same height. --}}
                            <p class="h-5 text-[14px] tabular-nums text-muted-foreground line-through decoration-muted-foreground/60"
                                @if($card['yearlyCompare']) :class="billingPeriod === 'yearly' ? 'visible' : 'invisible'" @else aria-hidden="true" @endif>
                                {{ $card['yearlyCompare'] ?? '' }}
                            </p>
                            <div class="mt-1 flex items-baseline gap-1.5 whitespace-nowrap">
                                <span class="text-[15px] font-medium text-muted-foreground">{{ $card['currency'] }}</span>
                                @if($card['free'])
                                    <span class="font-headline text-[38px] font-semibold leading-none tabular-nums">{{ $card['monthly'] }}</span>
                                    <span class="text-[14px] text-muted-foreground">/month</span>
                                @else
                                    <span class="font-headline text-[38px] font-semibold leading-none tabular-nums"
                                        x-text="billingPeriod === 'monthly' ? @js($card['monthly']) : @js($card['yearly'])">{{ $card['yearly'] }}</span>
                                    <span class="text-[14px] text-muted-foreground"
                                        x-text="billingPeriod === 'monthly' ? '/month' : @js($card['yearlyUnit'])">{{ $card['yearlyUnit'] }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-[13px] text-muted-foreground">
                                @if($card['free'])
                                    Free forever
                                @else
                                    <span x-text="billingPeriod === 'yearly' ? @js($card['yearlyNote']) : 'Billed monthly'">{{ $card['yearlyNote'] }}</span>
                                @endif
                            </p>
                        </div>

                        <a href="{{ route('register') }}" @class([
                            'btn btn-lg mt-6 w-full self-start',
                            'btn-primary' => $card['featured'],
                            'btn-outline' => !$card['featured'],
                        ])>{{ $card['cta'] }}</a>

                        <ul class="mt-6 space-y-3 border-t border-border pt-6">
                            @foreach($card['features'] as $feature)
                                <li class="flex items-start gap-2.5 text-[15px] leading-snug">
                                    <x-icon name="check" class="mt-0.5 size-4 shrink-0 {{ $card['featured'] ? 'text-brand-strong' : 'text-muted-foreground' }}" stroke-width="2.5" />
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <p class="mt-8 max-w-2xl text-[13px] leading-relaxed text-muted-foreground">
                Prices in {{ $plans->first()->currency }}. Large videos are optimized automatically. Storage and video allowances are subject to fair use.
            </p>
            @unless(request()->routeIs('pricing'))
                <a href="{{ route('pricing') }}" class="mt-2 inline-flex min-h-11 items-center gap-1.5 text-[15px] font-medium underline decoration-brand decoration-2 underline-offset-4 hover:decoration-foreground">
                    Compare every plan in detail <x-icon name="chevron-right" class="size-4" />
                </a>
            @endunless
        @endif
    </div>
</section>
