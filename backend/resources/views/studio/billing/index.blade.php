@extends('layouts.app', ['title' => 'Billing & plans · ifotoset'])

@php
    // Decimal (marketing) sizes: 50 GB, 1 TB, 3 TB, matching how plans are sold.
    $fmtSize = function ($bytes): string {
        $bytes = (float) $bytes;
        $gb = $bytes / (1024 ** 3);
        if ($gb >= 1000) {
            $tb = $gb / 1000;
            return rtrim(rtrim(number_format($tb, 1), '0'), '.') . ' TB';
        }
        if ($gb >= 1) {
            return rtrim(rtrim(number_format($gb, $gb >= 100 ? 0 : 1), '0'), '.') . ' GB';
        }
        $mb = $bytes / (1024 ** 2);
        if ($mb >= 1) {
            return number_format($mb, $mb >= 100 ? 0 : 1) . ' MB';
        }
        return number_format($bytes / 1024, 0) . ' KB';
    };
    $fmtDuration = function (int $seconds): string {
        if ($seconds <= 0) return '0 min';
        if ($seconds >= 3600) {
            $h = $seconds / 3600;
            return rtrim(rtrim(number_format($h, 1), '0'), '.') . ' h';
        }
        return round($seconds / 60) . ' min';
    };
    $money = fn ($amount) => number_format((float) $amount, 0);

    $currentSlug = $currentPlan->slug ?? 'free';
    $isFreePlan = !$currentPlan || $currentSlug === 'free';
    $currency = $currentPlan->currency ?? ($plans->first()->currency ?? 'RWF');
    $currentRank = $currentPlan ? (float) $currentPlan->monthly_price : 0;

    // Subscription state
    $subCycle = $activeSubscription->billing_cycle ?? 'monthly';
    $isAnnualSub = in_array($subCycle, ['annual', 'yearly'], true);
    $currentPrice = $currentPlan ? ($isAnnualSub && (float) $currentPlan->annual_price > 0 ? $currentPlan->annual_price : $currentPlan->monthly_price) : 0;
    $endsAt = $activeSubscription?->ends_at;
    $daysLeft = $endsAt ? max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)) : null;
    $cancelsAt = $activeSubscription?->cancels_at;

    // Usage
    $usedBytes = (int) ($storage['used_bytes'] ?? 0);
    $limitBytes = (int) ($storage['limit_bytes'] ?? 0);
    $storagePct = $limitBytes > 0 ? min(100, ($usedBytes / $limitBytes) * 100) : 0;

    $videoLimit = (int) ($currentPlan?->video_limit_seconds ?? 0);
    $videoUsed = (int) ($user->video_seconds_used ?? 0);
    $videoPct = $videoLimit > 0 ? min(100, ($videoUsed / $videoLimit) * 100) : 0;

    $galleryLimit = $currentPlan?->gallery_limit;
    $galleryCount = (int) ($galleryCount ?? 0);
    $galleryPct = $galleryLimit ? min(100, ($galleryCount / max(1, $galleryLimit)) * 100) : 0;

    $meterTone = fn ($pct) => $pct >= 90 ? '!bg-destructive' : ($pct >= 75 ? '!bg-warning' : '');

    // Best annual saving across paid plans (computed, not invented)
    $maxSaving = $plans->filter(fn ($p) => (float) $p->monthly_price > 0 && (float) $p->annual_price > 0)
        ->map(fn ($p) => (1 - ((float) $p->annual_price / ((float) $p->monthly_price * 12))) * 100)
        ->max();
    $maxSaving = $maxSaving ? (int) floor($maxSaving) : 0;

    $planBlurbs = [
        'free' => 'For hobbyists trying out client proofing.',
        'basic' => 'For rising solo photographers with regular sessions.',
        'pro' => 'For full-time studios and media houses.',
        'business' => 'Enterprise scale storage with priority support.',
    ];
    $returnQuery = !empty($returnTo) ? '&return_to=' . urlencode($returnTo) : '';
@endphp

@section('breadcrumbs')
    <span>Account</span>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Billing &amp; plans</span>
@endsection

@section('content')
<div x-data="{ cycle: 'monthly' }">
    <x-ui.page-header title="Billing & plans" description="Manage your subscription, track what you're using and download receipts." />

    {{-- Return-to-gallery banner (from an upload roadblock) --}}
    @if(!empty($returnTo))
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-info/25 bg-info/8 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-info/15 text-info"><x-icon name="upload-cloud" class="size-4" /></span>
                <div>
                    <p class="text-sm font-medium text-foreground">Your upload is paused</p>
                    <p class="text-[13px] text-muted-foreground">Pick a plan with more storage and your upload session resumes automatically.</p>
                </div>
            </div>
            <a href="{{ $returnTo }}" class="btn btn-secondary btn-sm shrink-0"><x-icon name="arrow-left" /> Back to gallery</a>
        </div>
    @endif

    {{-- Pending payment banner --}}
    @if($pendingPayment)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-warning/25 bg-warning/8 p-4 sm:flex-row sm:items-center sm:justify-between"
             x-data="{ checking: false, status: 'pending' }">
            <div class="flex items-start gap-3">
                <span class="relative mt-1.5 flex size-2.5 shrink-0">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-warning opacity-60"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-warning"></span>
                </span>
                <div>
                    <p class="text-sm font-medium text-foreground">Payment in progress</p>
                    <p class="text-[13px] text-muted-foreground">
                        {{ $pendingPayment->plan?->name ?? 'Plan' }} · <span class="tabular-nums">{{ $money($pendingPayment->amount) }} {{ $pendingPayment->currency }}</span>, started {{ $pendingPayment->created_at->diffForHumans() }}.
                    </p>
                </div>
            </div>
            @if($pendingPayment->plan)
                <a href="{{ route('studio.billing.checkout', $pendingPayment->plan->slug) }}?cycle={{ $pendingPayment->billing_cycle }}" class="btn btn-outline btn-sm shrink-0">
                    View status <x-icon name="arrow-right" />
                </a>
            @endif
        </div>
    @endif

    {{-- ============ Current plan + usage ============ --}}
    <div class="grid gap-4 lg:grid-cols-5">
        {{-- Plan hero --}}
        <section class="card relative overflow-hidden lg:col-span-3" aria-labelledby="current-plan-heading">
            <div class="pointer-events-none absolute inset-0 bg-dots opacity-50 mask-radial"></div>
            <div class="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-brand/10 blur-3xl"></div>

            <div class="relative flex h-full flex-col p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-xl border border-border bg-card text-brand shadow-xs">
                            <x-icon name="{{ $isFreePlan ? 'sparkles' : 'crown' }}" class="size-5" />
                        </span>
                        <div>
                            <p class="eyebrow">Current plan</p>
                            <h2 id="current-plan-heading" class="mt-0.5 text-xl font-semibold tracking-tight text-foreground">{{ $currentPlan->name ?? 'Free' }}</h2>
                        </div>
                    </div>
                    @if($isFreePlan)
                        <x-ui.badge variant="neutral">Free tier</x-ui.badge>
                    @elseif($activeSubscription && $cancelsAt)
                        <x-ui.badge variant="warning" dot>Cancels {{ $cancelsAt->format('M j') }}</x-ui.badge>
                    @elseif($activeSubscription && $daysLeft !== null && $daysLeft <= 7)
                        <x-ui.badge variant="warning" dot>Expires soon</x-ui.badge>
                    @elseif($activeSubscription)
                        <x-ui.badge variant="success" dot>Active</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral" dot>No active subscription</x-ui.badge>
                    @endif
                </div>

                <div class="mt-6 flex items-baseline gap-1.5">
                    @if($isFreePlan)
                        <span class="font-display text-4xl font-semibold tracking-tight text-foreground">Free</span>
                        <span class="text-sm text-muted-foreground">forever</span>
                    @else
                        <span class="font-display text-4xl font-semibold tracking-tight tabular-nums text-foreground">{{ $money($currentPrice) }}</span>
                        <span class="text-sm text-muted-foreground">{{ $currentPlan->currency }} / {{ $isAnnualSub ? 'year' : 'month' }}</span>
                    @endif
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-3">
                    <div class="bg-card/90 p-3">
                        <dt class="text-xs text-muted-foreground">Billing</dt>
                        <dd class="mt-0.5 text-sm font-medium text-foreground">
                            @if($isFreePlan) No charge @else {{ $isAnnualSub ? 'Yearly' : ($subCycle === 'monthly' ? 'Monthly' : \Illuminate\Support\Str::of($subCycle)->replace('_', ' ')->title()) }} @endif
                        </dd>
                    </div>
                    <div class="bg-card/90 p-3">
                        <dt class="text-xs text-muted-foreground">{{ $cancelsAt ? 'Ends on' : 'Renews / expires' }}</dt>
                        <dd class="mt-0.5 text-sm font-medium tabular-nums text-foreground">{{ $endsAt ? $endsAt->format('M j, Y') : '—' }}</dd>
                    </div>
                    <div class="col-span-2 bg-card/90 p-3 sm:col-span-1">
                        <dt class="text-xs text-muted-foreground">Time remaining</dt>
                        <dd class="mt-0.5 text-sm font-medium tabular-nums text-foreground">
                            @if($daysLeft !== null) {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} @elseif($isFreePlan) No expiry @else — @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-auto flex flex-wrap items-center gap-2 pt-6">
                    @if($currentSlug !== 'business')
                        <a href="#plans" class="btn btn-brand"><x-icon name="zap" /> {{ $isFreePlan ? 'Upgrade plan' : 'Change plan' }}</a>
                    @endif
                    @if(!$isFreePlan && $currentPlan)
                        <a href="{{ route('studio.billing.checkout', $currentPlan->slug) }}?cycle={{ $isAnnualSub ? 'annual' : 'monthly' }}{{ $returnQuery }}" class="btn btn-outline">
                            <x-icon name="refresh-cw" /> Extend {{ $currentPlan->name }}
                        </a>
                    @endif
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground sm:ml-auto">
                        <x-icon name="smartphone" class="size-3.5" /> Paid with MTN MoMo or Airtel Money
                    </p>
                </div>
            </div>
        </section>

        {{-- Usage meters --}}
        <section class="card lg:col-span-2" aria-labelledby="usage-heading">
            <div class="flex items-center justify-between border-b border-border px-5 py-4">
                <div>
                    <h2 id="usage-heading" class="text-[15px] font-semibold text-foreground">Usage</h2>
                    <p class="text-xs text-muted-foreground">Against your {{ $currentPlan->name ?? 'Free' }} allowance</p>
                </div>
                <x-icon name="gauge" class="size-4 text-muted-foreground" />
            </div>
            <div class="divide-y divide-border">
                {{-- Storage --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2 font-medium text-foreground"><x-icon name="hard-drive" class="size-4 text-muted-foreground" /> Storage</span>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            @if($limitBytes > 0)
                                <span class="font-medium text-foreground">{{ $fmtSize($usedBytes) }}</span> of {{ $fmtSize($limitBytes) }}
                            @else
                                <span class="font-medium text-foreground">{{ $fmtSize($usedBytes) }}</span> · Unlimited
                            @endif
                        </span>
                    </div>
                    <div class="progress mt-3" role="progressbar" aria-label="Storage used" aria-valuenow="{{ round($storagePct) }}" aria-valuemin="0" aria-valuemax="100">
                        <span style="width: {{ $limitBytes > 0 ? max(1.5, $storagePct) : 0 }}%" class="{{ $meterTone($storagePct) }}"></span>
                    </div>
                    <p class="mt-2 text-xs tabular-nums text-muted-foreground">
                        @if($limitBytes > 0)
                            {{ $storagePct > 0 && $storagePct < 1 ? 'Less than 1%' : round($storagePct) . '%' }} used
                            @if(($storage['percentage'] ?? 0) >= 90)
                                · <span class="text-destructive">Almost full</span>
                            @endif
                        @else
                            No storage cap on your plan
                        @endif
                    </p>
                </div>

                {{-- Video --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2 font-medium text-foreground"><x-icon name="video" class="size-4 text-muted-foreground" /> Video</span>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            @if($videoLimit > 0)
                                <span class="font-medium text-foreground">{{ $fmtDuration($videoUsed) }}</span> of {{ $fmtDuration($videoLimit) }}
                            @else
                                Not included
                            @endif
                        </span>
                    </div>
                    <div class="progress mt-3" role="progressbar" aria-label="Video minutes used" aria-valuenow="{{ round($videoPct) }}" aria-valuemin="0" aria-valuemax="100">
                        <span style="width: {{ $videoLimit > 0 ? max(1.5, $videoPct) : 0 }}%" class="{{ $meterTone($videoPct) }}"></span>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        @if($videoLimit > 0)
                            <span class="tabular-nums">{{ $videoPct > 0 && $videoPct < 1 ? 'Less than 1%' : round($videoPct) . '%' }}</span> of video delivery time used
                        @else
                            Upgrade to Basic or higher to deliver video
                        @endif
                    </p>
                </div>

                {{-- Galleries --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2 font-medium text-foreground"><x-icon name="images" class="size-4 text-muted-foreground" /> Galleries</span>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            <span class="font-medium text-foreground">{{ number_format($galleryCount) }}</span>
                            @if($galleryLimit) of {{ number_format($galleryLimit) }} @else · Unlimited @endif
                        </span>
                    </div>
                    <div class="progress mt-3" role="progressbar" aria-label="Galleries used">
                        <span style="width: {{ $galleryLimit ? max(1.5, $galleryPct) : 0 }}%" class="{{ $meterTone($galleryPct) }}"></span>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ $galleryLimit ? round($galleryPct) . '% of your gallery limit' : 'Create as many galleries as you like' }}
                    </p>
                </div>
            </div>
        </section>
    </div>

    {{-- ============ Plans ============ --}}
    <section id="plans" class="mt-10 scroll-mt-20" aria-labelledby="plans-heading">
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="plans-heading" class="text-lg font-semibold tracking-tight text-foreground">Compare plans</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">Every plan includes unlimited galleries, high-resolution downloads and Mobile Money checkout.</p>
            </div>
            <div class="tabs self-start sm:self-auto" role="tablist" aria-label="Billing period">
                <button type="button" role="tab" class="tab" :aria-selected="(cycle === 'monthly').toString()" @click="cycle = 'monthly'">Monthly</button>
                <button type="button" role="tab" class="tab" :aria-selected="(cycle === 'annual').toString()" @click="cycle = 'annual'">
                    Yearly
                    @if($maxSaving > 0)
                        <span class="rounded-full bg-success/12 px-1.5 py-px text-[10px] font-semibold text-success">Save up to {{ $maxSaving }}%</span>
                    @endif
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($plans as $plan)
                @php
                    $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                    $isFeatured = $plan->slug === 'pro' && !$isCurrent;
                    $isFree = $plan->slug === 'free';
                    $isUpgrade = (float) $plan->monthly_price > $currentRank;
                    $monthly = (float) $plan->monthly_price;
                    $annual = (float) $plan->annual_price;
                    $annualPerMonth = $annual > 0 ? $annual / 12 : 0;
                    $saving = ($monthly > 0 && $annual > 0) ? (int) floor((1 - $annual / ($monthly * 12)) * 100) : 0;
                    $planVideo = (int) $plan->video_limit_seconds;
                @endphp
                <div @class([
                        'relative flex flex-col rounded-xl border bg-card p-5 transition-all duration-200',
                        'border-brand/60 shadow-md ring-1 ring-brand/30' => $isFeatured,
                        'border-foreground/25 shadow-xs ring-1 ring-foreground/10' => $isCurrent,
                        'border-border shadow-xs hover:border-border-strong hover:shadow-sm' => !$isFeatured && !$isCurrent,
                    ])>
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-[15px] font-semibold text-foreground">{{ $plan->name }}</h3>
                        @if($isCurrent)
                            <x-ui.badge variant="solid">Current</x-ui.badge>
                        @elseif($isFeatured)
                            <x-ui.badge variant="brand"><x-icon name="star" class="size-3" /> Most popular</x-ui.badge>
                        @endif
                    </div>
                    <p class="mt-1 min-h-10 text-[13px] leading-snug text-muted-foreground">{{ $planBlurbs[$plan->slug] ?? 'Scale your studio with more storage.' }}</p>

                    <div class="mt-5 min-h-[68px]">
                        @if($isFree)
                            <div class="flex items-baseline gap-1">
                                <span class="font-display text-3xl font-semibold tracking-tight text-foreground">Free</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">No card or phone required</p>
                        @else
                            <div x-show="cycle === 'monthly'">
                                <div class="flex items-baseline gap-1">
                                    <span class="font-display text-3xl font-semibold tracking-tight tabular-nums text-foreground">{{ $money($monthly) }}</span>
                                    <span class="text-xs text-muted-foreground">{{ $plan->currency }} / mo</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">Billed monthly</p>
                            </div>
                            <div x-show="cycle === 'annual'" x-cloak>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-display text-3xl font-semibold tracking-tight tabular-nums text-foreground">{{ $money($annual > 0 ? $annual : $monthly * 12) }}</span>
                                    <span class="text-xs text-muted-foreground">{{ $plan->currency }} / yr</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    @if($annualPerMonth > 0)
                                        <span class="tabular-nums">≈ {{ $money($annualPerMonth) }}</span> / mo
                                        @if($saving > 0) · <span class="font-medium text-success">save {{ $saving }}%</span> @endif
                                    @else
                                        Billed yearly
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- CTA --}}
                    <div class="mt-5">
                        @if($isCurrent)
                            <button type="button" disabled class="btn btn-secondary w-full cursor-default opacity-70"><x-icon name="check" /> Your current plan</button>
                        @elseif($isFree)
                            <button type="button" disabled class="btn btn-secondary w-full cursor-default opacity-70">Included by default</button>
                        @elseif($isUpgrade)
                            <a :href="'{{ route('studio.billing.checkout', $plan->slug) }}?cycle=' + cycle + '{{ $returnQuery }}'"
                               class="btn {{ $isFeatured ? 'btn-brand' : 'btn-primary' }} w-full">
                                Upgrade to {{ $plan->name }} <x-icon name="arrow-right" />
                            </a>
                        @else
                            <a :href="'{{ route('studio.billing.checkout', $plan->slug) }}?cycle=' + cycle + '{{ $returnQuery }}'"
                               class="btn btn-outline w-full">
                                Switch to {{ $plan->name }}
                            </a>
                        @endif
                    </div>

                    <ul class="mt-5 space-y-2.5 border-t border-border pt-5 text-[13px]">
                        <li class="flex items-start gap-2.5 font-medium text-foreground">
                            <x-icon name="hard-drive" class="mt-0.5 size-4 shrink-0 text-brand" />
                            {{ $plan->storage_limit ? $fmtSize($plan->storage_limit) : 'Unlimited' }} storage
                        </li>
                        <li @class(['flex items-start gap-2.5', 'text-foreground' => $planVideo > 0, 'text-subtle' => $planVideo <= 0])>
                            <x-icon name="video" :class="'mt-0.5 size-4 shrink-0' . ($planVideo > 0 ? ' text-brand' : '')" />
                            {{ $planVideo > 0 ? $fmtDuration($planVideo) . ' of video delivery' : 'No video delivery' }}
                        </li>
                        <li class="flex items-start gap-2.5 text-muted-foreground">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" />
                            {{ $plan->gallery_limit ? number_format($plan->gallery_limit) . ' galleries' : 'Unlimited galleries' }}
                        </li>
                        <li class="flex items-start gap-2.5 text-muted-foreground">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> High-resolution downloads
                        </li>
                        <li class="flex items-start gap-2.5 text-muted-foreground">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Password &amp; PIN protection
                        </li>
                        @unless($isFree)
                            <li class="flex items-start gap-2.5 text-muted-foreground">
                                <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Custom subdomain portfolio
                            </li>
                        @endunless
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============ Payment history ============ --}}
    <section class="mt-10" aria-labelledby="history-heading">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 id="history-heading" class="text-lg font-semibold tracking-tight text-foreground">Payment history</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">Your subscription payments and receipts.</p>
            </div>
        </div>

        @if(($payments ?? collect())->isEmpty())
            <x-ui.empty-state icon="receipt" title="No payments yet"
                              description="When you upgrade, each Mobile Money payment and its receipt will appear here." />
        @else
            <div class="card overflow-hidden">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Plan</th>
                                <th class="hidden md:table-cell">Method</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th class="text-right"><span class="sr-only">Receipt</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $payment)
                                @php
                                    $prov = strtolower($payment->provider ?? '');
                                    $method = str_contains($prov, 'mtn') ? 'MTN MoMo' : (str_contains($prov, 'airtel') ? 'Airtel Money' : 'Mobile Money');
                                    $pMonths = $payment->metadata['months'] ?? ($payment->billing_cycle === 'annual' ? 12 : (preg_match('/^(\d+)_months$/', (string) $payment->billing_cycle, $m) ? (int) $m[1] : 1));
                                    $statusMap = [
                                        'completed' => ['success', 'Paid'],
                                        'pending' => ['warning', 'Pending'],
                                        'failed' => ['danger', 'Failed'],
                                        'verification_failed' => ['danger', 'Failed'],
                                    ];
                                    [$sVariant, $sLabel] = $statusMap[$payment->status] ?? ['neutral', ucfirst(str_replace('_', ' ', (string) $payment->status))];
                                    $phone = (string) $payment->phone_number;
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <div class="font-medium text-foreground tabular-nums">{{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y') }}</div>
                                        <div class="text-xs text-muted-foreground tabular-nums">{{ ($payment->paid_at ?? $payment->created_at)->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-foreground">{{ $payment->plan?->name ?? 'Subscription' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $pMonths }} {{ \Illuminate\Support\Str::plural('month', (int) $pMonths) }}</div>
                                    </td>
                                    <td class="hidden md:table-cell">
                                        <div class="text-foreground">{{ $method }}</div>
                                        @if($phone)
                                            <div class="text-xs tabular-nums text-muted-foreground">•••• {{ substr($phone, -4) }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right font-medium tabular-nums text-foreground">{{ $money($payment->amount) }} {{ $payment->currency }}</td>
                                    <td><x-ui.badge :variant="$sVariant" dot>{{ $sLabel }}</x-ui.badge></td>
                                    <td class="text-right">
                                        @if($payment->status === 'completed')
                                            <a href="{{ route('studio.billing.receipt', $payment->uuid) }}" class="btn btn-ghost btn-sm"><x-icon name="receipt" /> <span class="hidden sm:inline">Receipt</span></a>
                                        @elseif($payment->status === 'pending' && $payment->plan)
                                            <a href="{{ route('studio.billing.checkout', $payment->plan->slug) }}?cycle={{ $payment->billing_cycle }}" class="btn btn-ghost btn-sm">Resume</a>
                                        @else
                                            <span class="text-xs text-subtle">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <p class="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground">
            <x-icon name="life-buoy" class="size-3.5" />
            Questions about a charge? Email <a href="mailto:support@ifotoset.com" class="link">support@ifotoset.com</a>.
        </p>
    </section>
</div>
@endsection
