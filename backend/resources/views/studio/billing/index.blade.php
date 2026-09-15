@extends('layouts.app', ['title' => 'Billing & Plans - Studio'])

@section('content')
<div class="max-w-6xl mx-auto space-y-10" x-data="{ cycle: 'monthly' }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-foreground">Subscription &amp; Storage Plans</h1>
            <p class="text-sm text-muted-foreground mt-1">Upgrade your cloud storage tier for client proofing and raw deliverables. All plans include unlimited galleries.</p>
        </div>
        <!-- Billing Cycle Toggle -->
        <div class="inline-flex items-center bg-secondary/70 p-1.5 rounded-xl border border-border">
            <button type="button"
                    @click="cycle = 'monthly'"
                    :class="cycle === 'monthly' ? 'bg-background text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    class="px-4 py-2 text-xs rounded-lg transition-all">
                Monthly Billing
            </button>
            <button type="button"
                    @click="cycle = 'annual'"
                    :class="cycle === 'annual' ? 'bg-background text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    class="px-4 py-2 text-xs rounded-lg transition-all flex items-center gap-1.5">
                Annual Billing
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary">Save ~18%</span>
            </button>
        </div>
    </div>

    <!-- Active Pending Payment Banner (if any) -->
    @if($pendingPayment)
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-between gap-4 text-xs"
             x-data="{ checking: false, status: 'pending' }">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                </span>
                <div>
                    <span class="font-bold text-amber-700 dark:text-amber-300">Payment in progress:</span>
                    <span class="text-muted-foreground ml-1">{{ $pendingPayment->plan?->name }} ({{ number_format($pendingPayment->amount, 0) }} {{ $pendingPayment->currency }}) initiated {{ $pendingPayment->created_at->diffForHumans() }}.</span>
                </div>
            </div>
            <a href="{{ route('studio.billing.checkout', $pendingPayment->plan->slug) }}?cycle={{ $pendingPayment->billing_cycle }}"
               class="font-semibold text-amber-700 dark:text-amber-300 hover:underline">
                View Status &rarr;
            </a>
        </div>
    @endif

    <!-- Current Storage Quota Bar -->
    <div class="bg-card border border-border rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Current Plan</span>
                <div class="text-xl font-bold text-foreground mt-0.5 flex items-center gap-2">
                    {{ $currentPlan->name ?? 'Free Tier' }}
                    @if($activeSubscription)
                        <span class="text-xs font-normal text-muted-foreground">
                            &bull; Renews/Expires {{ $activeSubscription->ends_at->format('M j, Y') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="text-right sm:text-right">
                <span class="text-xs font-semibold text-muted-foreground">Storage Allocation</span>
                <div class="text-base font-bold text-primary">
                    {{ round($storage['percentage'] ?? 0) }}% Used
                </div>
            </div>
        </div>

        <div class="w-full bg-secondary h-2.5 rounded-full overflow-hidden">
            <div class="bg-primary h-full rounded-full transition-all duration-500"
                 style="width: {{ min(100, max(2, $storage['percentage'] ?? 0)) }}%"></div>
        </div>

        <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span>{{ round(($storage['used_bytes'] ?? 0) / (1024 * 1024), 1) }} MB Used</span>
            <span>{{ round(($storage['limit_bytes'] ?? 2147483648) / (1024 * 1024 * 1024), 1) }} GB Quota</span>
        </div>
    </div>

    <!-- Pricing Cards Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($plans as $plan)
            @php
                $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                $isPro = $plan->slug === 'pro';
            @endphp
            <div class="relative rounded-3xl border {{ $isPro ? 'border-primary shadow-xl bg-card' : 'border-border bg-card/60' }} p-6 flex flex-col justify-between transition-all hover:shadow-md">
                @if($isPro)
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full text-[11px] font-bold bg-primary text-primary-foreground tracking-wide uppercase shadow-sm">
                        Most Popular
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-bold text-foreground">{{ $plan->name }}</h3>
                        <p class="text-xs text-muted-foreground mt-1 min-h-[32px]">
                            @if($plan->slug === 'free')
                                For hobbyists and beginners testing client proofing.
                            @elseif($plan->slug === 'basic')
                                Ideal for rising solo photographers with regular sessions.
                            @elseif($plan->slug === 'pro')
                                Designed for full-time professional studios & media houses.
                            @else
                                Enterprise scale with massive storage and priority support.
                            @endif
                        </p>
                    </div>

                    <!-- Price -->
                    <div class="pt-2 border-t border-border">
                        <div x-show="cycle === 'monthly'">
                            <span class="text-3xl font-extrabold text-foreground">
                                {{ $plan->monthly_price > 0 ? number_format($plan->monthly_price, 0) : 'Free' }}
                            </span>
                            @if($plan->monthly_price > 0)
                                <span class="text-xs text-muted-foreground">RWF / month</span>
                            @endif
                        </div>
                        <div x-show="cycle === 'annual'" style="display: none;">
                            <span class="text-3xl font-extrabold text-foreground">
                                {{ $plan->annual_price > 0 ? number_format($plan->annual_price, 0) : 'Free' }}
                            </span>
                            @if($plan->annual_price > 0)
                                <span class="text-xs text-muted-foreground">RWF / year</span>
                            @endif
                        </div>
                    </div>

                    <!-- Feature Bullet Points -->
                    <ul class="space-y-2.5 text-xs text-muted-foreground pt-4 border-t border-border">
                        <li class="flex items-center gap-2 text-foreground font-semibold">
                            <span class="text-primary font-bold">&check;</span>
                            @if($plan->slug === 'free')
                                2 GB Cloud Storage
                            @elseif($plan->slug === 'basic')
                                50 GB Cloud Storage
                            @elseif($plan->slug === 'pro')
                                1 TB (1,000 GB) Storage
                            @else
                                3 TB (3,000 GB) Storage
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">&check;</span>
                            <strong class="text-foreground">Unlimited</strong> Galleries &amp; Albums
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">&check;</span>
                            High-Resolution Downloads
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">&check;</span>
                            Password &amp; PIN Protection
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">&check;</span>
                            Instant Mobile Money (MTN/Airtel)
                        </li>
                        @if($plan->slug !== 'free')
                            <li class="flex items-center gap-2 text-foreground">
                                <span class="text-primary font-bold">&check;</span>
                                Custom Subdomain Portfolio
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- CTA Button -->
                <div class="pt-6 mt-6 border-t border-border">
                    @if($isCurrent)
                        <button disabled class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-secondary text-muted-foreground cursor-not-allowed">
                            Current Active Plan
                        </button>
                    @elseif($plan->slug === 'free')
                        <button disabled class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-secondary text-muted-foreground cursor-not-allowed">
                            Included by Default
                        </button>
                    @else
                        <a :href="'{{ route('studio.billing.checkout', $plan->slug) }}?cycle=' + cycle"
                           class="block text-center w-full py-2.5 px-4 rounded-xl text-xs font-bold {{ $isPro ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'bg-secondary text-foreground hover:bg-secondary/80' }} transition-colors shadow-sm">
                            Upgrade to {{ $plan->name }}
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
