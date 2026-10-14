@extends('layouts.app', ['title' => 'Checkout · ' . $plan->name . ' · ifotoset'])

@php
    $fmtSize = function ($bytes): string {
        $gb = ((float) $bytes) / (1024 ** 3);
        if ($gb >= 1000) return rtrim(rtrim(number_format($gb / 1000, 1), '0'), '.') . ' TB';
        return rtrim(rtrim(number_format($gb, $gb >= 100 ? 0 : 1), '0'), '.') . ' GB';
    };
    $videoSeconds = (int) $plan->video_limit_seconds;
    $videoLabel = $videoSeconds >= 3600
        ? rtrim(rtrim(number_format($videoSeconds / 3600, 1), '0'), '.') . ' h of video delivery'
        : ($videoSeconds > 0 ? round($videoSeconds / 60) . ' min of video delivery' : null);
    $annualSaving = ($annualPrice > 0 && $monthlyPrice > 0 && $annualPrice < $monthlyPrice * 12)
        ? (int) floor((1 - $annualPrice / ($monthlyPrice * 12)) * 100)
        : 0;
@endphp

@section('breadcrumbs')
    <a href="{{ route('studio.billing.index') }}" class="transition hover:text-foreground">Billing &amp; plans</a>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Checkout</span>
@endsection

@section('content')
<div class="mx-auto max-w-5xl"
     x-data="checkoutFlow({
         planSlug: '{{ $plan->slug }}',
         planName: '{{ $plan->name }}',
         billingCycle: '{{ $billingCycle }}',
         monthlyPrice: {{ (float) $monthlyPrice }},
         annualPrice: {{ (float) $annualPrice }},
         months: {{ (int) $months }},
         currency: '{{ $plan->currency }}',
         defaultPhone: '{{ $user->phone ?? '' }}',
         initiateUrl: '{{ route('studio.billing.initiate') }}',
         billingUrl: '{{ route('studio.billing.index') }}',
         returnTo: '{{ $returnTo ?? '' }}'
     })">

    {{-- Back navigation + heading --}}
    <div class="mb-8">
        <a :href="returnTo || '{{ route('studio.billing.index') }}'" class="mb-3 inline-flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground transition hover:text-foreground">
            <x-icon name="arrow-left" class="size-3.5" />
            <span x-text="returnTo ? 'Back to gallery' : 'Back to plans'">Back to plans</span>
        </a>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-foreground sm:text-[28px]">Upgrade to {{ $plan->name }}</h1>
            <span class="badge badge-neutral"><x-icon name="lock" class="size-3" /> Secure checkout</span>
        </div>
        <p class="mt-1.5 text-sm text-muted-foreground">Pay instantly with MTN Mobile Money or Airtel Money. Your plan activates as soon as you approve.</p>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">

        {{-- ================= Order summary ================= --}}
        <aside class="card overflow-hidden lg:sticky lg:top-6 lg:order-2" aria-label="Order summary">
            <div class="relative border-b border-border p-5">
                <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
                <div class="relative flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20">
                        <x-icon name="crown" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="eyebrow">Order summary</p>
                        <p class="mt-0.5 text-[15px] font-semibold text-foreground">{{ $plan->name }} plan</p>
                        <p class="text-xs text-muted-foreground" x-text="durationSummary"></p>
                    </div>
                </div>
                <ul class="relative mt-4 space-y-2 text-[13px] text-muted-foreground">
                    <li class="flex items-center gap-2"><x-icon name="hard-drive" class="size-3.5 text-foreground/70" /> {{ $plan->storage_limit ? $fmtSize($plan->storage_limit) : 'Unlimited' }} cloud storage</li>
                    @if($videoLabel)
                        <li class="flex items-center gap-2"><x-icon name="video" class="size-3.5 text-foreground/70" /> {{ $videoLabel }}</li>
                    @endif
                    <li class="flex items-center gap-2"><x-icon name="images" class="size-3.5 text-foreground/70" /> {{ $plan->gallery_limit ? number_format($plan->gallery_limit) . ' galleries' : 'Unlimited galleries' }}</li>
                </ul>
            </div>

            <dl class="space-y-2.5 p-5 text-[13px]">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Monthly price</dt>
                    <dd class="tabular-nums text-foreground">{{ number_format($monthlyPrice, 0) }} {{ $plan->currency }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Duration</dt>
                    <dd class="tabular-nums text-foreground" x-text="durationLabel">{{ $months }} {{ \Illuminate\Support\Str::plural('month', $months) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4" x-show="months === 12 && annualPrice > 0 && annualPrice < monthlyPrice * 12" x-cloak>
                    <dt class="flex items-center gap-1.5 text-success"><x-icon name="tag" class="size-3.5" /> Annual discount</dt>
                    <dd class="tabular-nums text-success" x-text="'− ' + new Intl.NumberFormat('en-US').format(monthlyPrice * 12 - annualPrice) + ' ' + currency"></dd>
                </div>
                <div class="!mt-4 flex items-end justify-between gap-4 border-t border-dashed border-border pt-4">
                    <dt>
                        <span class="block text-sm font-medium text-foreground">Total due today</span>
                        <span class="block text-xs text-muted-foreground" x-text="billingSubtext">Billed {{ $billingCycle }}</span>
                    </dt>
                    <dd class="text-right">
                        <span class="font-display text-2xl font-semibold tracking-tight tabular-nums text-foreground" x-text="formattedPrice">{{ number_format($price, 0) }}</span>
                        <span class="text-xs text-muted-foreground" x-text="currency">{{ $plan->currency }}</span>
                    </dd>
                </div>
            </dl>

            <div class="flex items-start gap-2 border-t border-border bg-muted/40 px-5 py-3.5 text-xs text-muted-foreground">
                <x-icon name="shield-check" class="mt-px size-4 shrink-0 text-success" />
                <span>One-time Mobile Money payment. Nothing renews automatically, so you'll never be charged without approving it on your phone.</span>
            </div>
        </aside>

        {{-- ================= Payment column ================= --}}
        <div class="min-w-0 lg:order-1">

            {{-- Step 1: payment form --}}
            <div x-show="step === 'input'" class="card">
                <form @submit.prevent="startPayment">
                    {{-- Failure / error state --}}
                    <div x-show="errorMessage" x-cloak class="border-b border-destructive/20 bg-destructive/8 px-5 py-4 sm:px-6" role="alert">
                        <div class="flex items-start gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-destructive/15 text-destructive"><x-icon name="circle-x" class="size-4" /></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground">Payment didn't go through</p>
                                <p class="mt-0.5 text-[13px] text-muted-foreground" x-text="errorMessage"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Duration --}}
                    <fieldset class="border-b border-border p-5 sm:p-6">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <legend class="text-sm font-medium text-foreground"><span class="mr-2 inline-flex size-5 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground">1</span>Duration</legend>
                            <span class="text-xs text-muted-foreground" x-text="durationSummary"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach([1 => '1 month', 3 => '3 months', 6 => '6 months', 12 => '12 months'] as $m => $label)
                                <button type="button"
                                        @click="setMonths({{ $m }})"
                                        :aria-pressed="(months === {{ $m }}).toString()"
                                        :class="months === {{ $m }} ? 'border-foreground bg-card ring-1 ring-foreground text-foreground' : 'border-border bg-card text-muted-foreground hover:border-border-strong hover:text-foreground'"
                                        class="relative flex h-14 flex-col items-center justify-center rounded-lg border text-[13px] font-medium transition-all">
                                    <span>{{ $label }}</span>
                                    @if($m === 12 && $annualSaving > 0)
                                        <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-success">Save {{ $annualSaving }}%</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 px-3 py-2">
                            <span class="text-[13px] text-muted-foreground"><span class="hidden sm:inline">Or choose exact months</span><span class="sm:hidden">Exact months</span></span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="decrementMonths()" :disabled="months <= 1" class="btn btn-outline btn-icon btn-sm disabled:pointer-events-none disabled:opacity-40" aria-label="Fewer months">
                                    <x-icon name="minus" />
                                </button>
                                <span class="min-w-20 text-center text-sm font-medium tabular-nums text-foreground" x-text="months + (months === 1 ? ' month' : ' months')"></span>
                                <button type="button" @click="incrementMonths()" :disabled="months >= 36" class="btn btn-outline btn-icon btn-sm disabled:pointer-events-none disabled:opacity-40" aria-label="More months">
                                    <x-icon name="plus" />
                                </button>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Operator + phone --}}
                    <fieldset class="p-5 sm:p-6">
                        <legend class="sr-only">Mobile Money account</legend>
                        <p class="mb-3 text-sm font-medium text-foreground"><span class="mr-2 inline-flex size-5 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground">2</span>Mobile Money network</p>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {{-- MTN --}}
                            <button type="button"
                                    @click="detectedProvider = 'MTN_MOMO_RWA'"
                                    :aria-pressed="(detectedProvider === 'MTN_MOMO_RWA').toString()"
                                    :class="detectedProvider === 'MTN_MOMO_RWA' ? 'border-foreground ring-1 ring-foreground' : 'border-border hover:border-border-strong'"
                                    class="group relative flex items-center gap-3 rounded-xl border bg-card p-3 text-left transition-all">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-warning/15 text-[11px] font-black tracking-tight text-warning ring-1 ring-inset ring-warning/30" aria-hidden="true">MTN</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-foreground">MTN MoMo</span>
                                    <span class="block text-xs tabular-nums text-muted-foreground">Numbers starting 078 · 079</span>
                                </span>
                                <span :class="detectedProvider === 'MTN_MOMO_RWA' ? 'border-foreground bg-foreground text-background' : 'border-border-strong text-transparent'"
                                      class="flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors">
                                    <x-icon name="check" class="size-3" stroke-width="3" />
                                </span>
                            </button>

                            {{-- Airtel --}}
                            <button type="button"
                                    @click="detectedProvider = 'AIRTEL_RWA'"
                                    :aria-pressed="(detectedProvider === 'AIRTEL_RWA').toString()"
                                    :class="detectedProvider === 'AIRTEL_RWA' ? 'border-foreground ring-1 ring-foreground' : 'border-border hover:border-border-strong'"
                                    class="group relative flex items-center gap-3 rounded-xl border bg-card p-3 text-left transition-all">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-destructive/12 text-xl font-black italic text-destructive ring-1 ring-inset ring-destructive/25" aria-hidden="true">a</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-foreground">Airtel Money</span>
                                    <span class="block text-xs tabular-nums text-muted-foreground">Numbers starting 072 · 073</span>
                                </span>
                                <span :class="detectedProvider === 'AIRTEL_RWA' ? 'border-foreground bg-foreground text-background' : 'border-border-strong text-transparent'"
                                      class="flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors">
                                    <x-icon name="check" class="size-3" stroke-width="3" />
                                </span>
                            </button>
                        </div>

                        <label for="checkout-phone" class="label mt-5">Phone number</label>
                        <div class="flex h-11 items-stretch overflow-hidden rounded-lg border border-border bg-input shadow-xs transition-[border-color,box-shadow] hover:border-border-strong focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15">
                            <span class="flex shrink-0 select-none items-center gap-2 border-r border-border bg-muted/50 px-3 text-sm text-foreground">
                                <span class="text-base leading-none" aria-hidden="true">🇷🇼</span>
                                <span class="font-medium tabular-nums text-muted-foreground">+250</span>
                            </span>
                            <input type="tel"
                                   id="checkout-phone"
                                   x-model="phone"
                                   @input="handlePhoneInput"
                                   required
                                   inputmode="numeric"
                                   autocomplete="tel"
                                   placeholder="788 000 000"
                                   class="min-w-0 flex-1 bg-transparent px-3 text-[15px] tracking-wide text-foreground tabular-nums placeholder:text-subtle focus:outline-none">
                        </div>
                        <p class="help flex items-center gap-1.5">
                            <template x-if="detectedProvider">
                                <span class="flex items-center gap-1.5">
                                    <span class="size-1.5 rounded-full" :class="detectedProvider === 'MTN_MOMO_RWA' ? 'bg-warning' : 'bg-destructive'"></span>
                                    <span x-text="detectedProvider === 'MTN_MOMO_RWA' ? 'MTN Mobile Money' : 'Airtel Money'"></span>
                                    <span class="hidden text-subtle sm:inline">· the payment prompt will be sent to this number</span>
                                </span>
                            </template>
                            <template x-if="!detectedProvider">
                                <span>We'll detect your network from the number.</span>
                            </template>
                        </p>

                        <button type="submit" :disabled="isInitiating" class="btn btn-primary btn-lg mt-6 w-full disabled:opacity-60">
                            <span x-show="!isInitiating" class="inline-flex items-center gap-2">
                                <x-icon name="lock" />
                                <span x-text="'Pay ' + formattedPrice + ' ' + currency">Pay {{ number_format($price, 0) }} {{ $plan->currency }}</span>
                            </span>
                            <span x-show="isInitiating" x-cloak class="inline-flex items-center gap-2">
                                <x-icon name="loader-circle" class="animate-spin" />
                                Sending prompt to your phone…
                            </span>
                        </button>

                        <p class="mt-4 text-center text-xs text-muted-foreground">
                            <x-icon name="shield-check" class="-mt-0.5 mr-1 inline size-3.5 text-success" />
                            Processed securely by PawaPay. We never see your PIN.
                        </p>
                    </fieldset>
                </form>
            </div>

            {{-- Step 2: waiting for approval --}}
            <div x-show="step === 'waiting'" x-cloak class="card overflow-hidden">
                <div class="relative px-5 pb-6 pt-10 text-center sm:px-8">
                    <div class="pointer-events-none absolute inset-0 bg-grid opacity-40 mask-radial"></div>

                    <div class="relative mx-auto flex size-20 items-center justify-center">
                        <span class="absolute inset-0 animate-ping rounded-full bg-brand/15 [animation-duration:2s]"></span>
                        <span class="absolute inset-2 rounded-full bg-brand/10"></span>
                        <span class="relative flex size-14 items-center justify-center rounded-2xl border border-border bg-card text-brand shadow-sm">
                            <x-icon name="smartphone" class="size-6" />
                        </span>
                    </div>

                    <h2 class="relative mt-6 text-xl font-semibold tracking-tight text-foreground">Approve the prompt on your phone</h2>
                    <p class="relative mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                        We sent a Mobile Money request to <span class="whitespace-nowrap font-medium tabular-nums text-foreground" x-text="formattedDisplayPhone"></span>.
                        Enter your PIN to confirm <span class="font-medium tabular-nums text-foreground" x-text="formattedPrice + ' ' + currency"></span>.
                    </p>

                    <div class="relative mt-5 inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground shadow-xs" role="status" aria-live="polite">
                        <x-icon name="loader-circle" class="size-3.5 animate-spin text-brand" />
                        <span>Waiting for confirmation</span>
                        <span class="font-medium tabular-nums text-foreground" x-text="elapsedSeconds + 's'"></span>
                    </div>
                </div>

                {{-- Progress steps --}}
                <ol class="grid border-y border-border bg-muted/30 text-xs sm:grid-cols-3">
                    <li class="flex items-center gap-2.5 border-b border-border px-5 py-3 sm:border-b-0 sm:border-r">
                        <span class="flex size-5 items-center justify-center rounded-full bg-success text-background"><x-icon name="check" class="size-3" stroke-width="3" /></span>
                        <span class="font-medium text-foreground">Prompt sent</span>
                    </li>
                    <li class="flex items-center gap-2.5 border-b border-border px-5 py-3 sm:border-b-0 sm:border-r">
                        <span class="flex size-5 items-center justify-center rounded-full border-2 border-brand"><span class="size-1.5 animate-pulse rounded-full bg-brand"></span></span>
                        <span class="font-medium text-foreground">Approve with your PIN</span>
                    </li>
                    <li class="flex items-center gap-2.5 px-5 py-3">
                        <span class="flex size-5 items-center justify-center rounded-full border border-border-strong"></span>
                        <span class="text-muted-foreground">Plan activated</span>
                    </li>
                </ol>

                <div class="space-y-4 p-5 sm:p-6">
                    {{-- Recap --}}
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-border p-3.5">
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">Amount</p>
                            <p class="text-base font-semibold tabular-nums text-foreground" x-text="formattedPrice + ' ' + currency"></p>
                            <p class="truncate text-xs text-muted-foreground" x-text="planName + ' plan · ' + durationLabel"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">Paying from</p>
                            <p class="whitespace-nowrap text-sm font-medium tabular-nums text-foreground" x-text="formattedDisplayPhone"></p>
                            <span x-show="detectedProvider === 'MTN_MOMO_RWA'" class="badge badge-warning mt-1">MTN MoMo</span>
                            <span x-show="detectedProvider === 'AIRTEL_RWA'" class="badge badge-danger mt-1">Airtel Money</span>
                        </div>
                    </div>

                    {{-- Manual fallback --}}
                    <div x-data="{ open: false }" class="rounded-lg border border-border">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="flex w-full items-center justify-between gap-3 px-3.5 py-3 text-left text-[13px] font-medium text-foreground">
                            <span class="flex items-center gap-2"><x-icon name="help-circle" class="size-4 text-muted-foreground" /> Didn't get the prompt?</span>
                            <x-icon name="chevron-down" class="size-4 text-muted-foreground transition-transform" ::class="open && 'rotate-180'" />
                        </button>
                        <div x-show="open" x-collapse x-cloak class="border-t border-border px-3.5 py-3">
                            <ol class="space-y-2 text-[13px] text-muted-foreground">
                                <li class="flex items-center gap-2.5">
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground">1</span>
                                    <span>Dial <a href="tel:*182*7*1%23" class="font-medium tabular-nums text-foreground underline decoration-border-strong underline-offset-4 hover:decoration-foreground" title="Tap to dial">*182*7*1#</a> on your phone</span>
                                    <button type="button" @click="copyUssdCode()" class="btn btn-ghost btn-xs ml-auto" :aria-label="ussdCopied ? 'Copied' : 'Copy code'">
                                        <x-icon name="copy" x-show="!ussdCopied" />
                                        <x-icon name="check" x-show="ussdCopied" x-cloak class="text-success" />
                                    </button>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground">2</span>
                                    Enter your Mobile Money PIN
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground">3</span>
                                    Select the pending payment to confirm
                                </li>
                            </ol>
                        </div>
                    </div>

                    {{-- Timeout notice --}}
                    <div x-show="isTimedOut" x-cloak class="alert alert-warning">
                        <x-icon name="clock" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">Still waiting for confirmation</p>
                            <p class="mt-0.5 text-[13px] text-muted-foreground">If you already entered your PIN, you can safely leave this page. Your plan activates automatically as soon as confirmation arrives.</p>
                            <a href="{{ route('studio.billing.index') }}" class="btn btn-outline btn-sm mt-3">View billing <x-icon name="arrow-right" /></a>
                        </div>
                    </div>

                    <p class="text-center text-[13px] text-muted-foreground">
                        Wrong number?
                        <button type="button" @click="cancelWaiting()" class="link">Change phone number</button>
                    </p>
                </div>
            </div>

            {{-- Step 3: success --}}
            <div x-show="step === 'success'" x-cloak class="card overflow-hidden">
                <div class="relative px-5 py-10 text-center sm:px-8">
                    <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
                    <div class="relative mx-auto flex size-16 items-center justify-center rounded-full bg-success/12 text-success ring-8 ring-success/5 animate-scale-in">
                        <x-icon name="check" class="size-8" stroke-width="2.5" />
                    </div>
                    <h2 class="relative mt-6 text-2xl font-semibold tracking-tight text-foreground">Payment successful</h2>
                    <p class="relative mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        You're now on <span class="font-medium text-foreground">{{ $plan->name }}</span> for <span class="font-medium text-foreground" x-text="durationLabel"></span>.
                        Your new storage and features are active right away.
                    </p>

                    <div class="relative mt-8 flex flex-col-reverse items-stretch justify-center gap-2 sm:flex-row sm:items-center">
                        <a :href="'/studio/billing/receipt/' + paymentUuid" class="btn btn-outline"><x-icon name="receipt" /> View receipt</a>
                        <template x-if="returnTo">
                            <a :href="returnToWithStatus" class="btn btn-primary">Return to gallery &amp; resume upload <x-icon name="arrow-right" /></a>
                        </template>
                        <template x-if="!returnTo">
                            <a href="{{ route('studio.dashboard') }}" class="btn btn-primary">Go to dashboard <x-icon name="arrow-right" /></a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkoutFlow(config) {
    return {
        step: 'input',
        planSlug: config.planSlug,
        planName: config.planName,
        billingCycle: config.billingCycle,
        monthlyPrice: Number(config.monthlyPrice) || 0,
        annualPrice: Number(config.annualPrice) || 0,
        months: Number(config.months) || 1,
        currency: config.currency,
        returnTo: config.returnTo || '',
        get returnToWithStatus() {
            if (!this.returnTo) return '';
            const sep = this.returnTo.includes('?') ? '&' : '?';
            return this.returnTo + sep + 'billing_return=success';
        },
        phone: '',
        detectedProvider: null,
        isInitiating: false,
        paymentUuid: null,
        errorMessage: null,
        elapsedSeconds: 0,
        timerInterval: null,
        pollInterval: null,
        isTimedOut: false,
        ussdCopied: false,

        copyUssdCode() {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText('*182*7*1#');
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = '*182*7*1#';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
            } catch (e) {
                // Clipboard fallback
            }
            this.ussdCopied = true;
            setTimeout(() => { this.ussdCopied = false; }, 2500);
        },

        cancelWaiting() {
            if (this.timerInterval) clearInterval(this.timerInterval);
            this.step = 'input';
        },

        init() {
            // Strip any country code from initial phone so it cleanly pairs with the prefix badge
            let initial = (config.defaultPhone || '').trim();
            initial = initial.replace(/^(\+250|250)/, '');
            this.phone = initial;
            this.detectProvider();
        },

        setMonths(m) {
            this.months = m;
        },

        incrementMonths() {
            if (this.months < 36) {
                this.months++;
            }
        },

        decrementMonths() {
            if (this.months > 1) {
                this.months--;
            }
        },

        get currentPrice() {
            if (this.months === 12 && this.annualPrice > 0) {
                return this.annualPrice;
            }
            return this.monthlyPrice * this.months;
        },

        get formattedPrice() {
            return new Intl.NumberFormat('en-US').format(this.currentPrice);
        },

        get durationLabel() {
            if (this.months === 1) return '1 month';
            if (this.months === 12) return '12 months (1 year)';
            return `${this.months} months`;
        },

        get durationSummary() {
            const days = this.months === 12 ? 365 : (this.months * 30);
            return `${days} days of access`;
        },

        get billingSubtext() {
            if (this.months === 1) return 'Billed monthly';
            if (this.months === 12 && this.annualPrice > 0) return 'Annual discount applied';
            return `${new Intl.NumberFormat('en-US').format(this.monthlyPrice)} ${this.currency} × ${this.months} mos`;
        },

        get fullPhoneNumber() {
            const clean = (this.phone || '').replace(/[^0-9]/g, '');
            if (clean.startsWith('250')) return clean;
            if (clean.startsWith('0')) return '250' + clean.substring(1);
            return '250' + clean;
        },

        get formattedDisplayPhone() {
            const clean = (this.phone || '').replace(/[^0-9]/g, '');
            let local = clean;
            if (local.startsWith('250')) local = local.substring(3);
            if (!local.startsWith('0')) local = '0' + local;
            return `+250 ${local.slice(1, 4)} ${local.slice(4, 7)} ${local.slice(7, 10)}`.trim();
        },

        handlePhoneInput() {
            // Strip any pasted +250 or 250 prefix
            let val = (this.phone || '').replace(/\s+/g, '');
            if (val.startsWith('+250')) {
                val = val.substring(4);
            } else if (val.startsWith('250') && val.length > 9) {
                val = val.substring(3);
            }
            this.phone = val;
            this.detectProvider();
        },

        detectProvider() {
            const clean = (this.phone || '').replace(/[^0-9]/g, '');
            if (clean.startsWith('25078') || clean.startsWith('25079') || clean.startsWith('078') || clean.startsWith('079') || clean.startsWith('78') || clean.startsWith('79')) {
                this.detectedProvider = 'MTN_MOMO_RWA';
            } else if (clean.startsWith('25072') || clean.startsWith('25073') || clean.startsWith('072') || clean.startsWith('073') || clean.startsWith('72') || clean.startsWith('73')) {
                this.detectedProvider = 'AIRTEL_RWA';
            } else {
                this.detectedProvider = null;
            }
        },

        async startPayment() {
            this.isInitiating = true;
            this.errorMessage = null;

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch(config.initiateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_slug: this.planSlug,
                        billing_cycle: this.months === 12 ? 'annual' : (this.months === 1 ? 'monthly' : `${this.months}_months`),
                        months: this.months,
                        phone_number: this.fullPhoneNumber,
                        provider: this.detectedProvider
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    let msg = data.message || 'Failed to initiate payment.';
                    if (data.errors && typeof data.errors === 'object') {
                        const firstKey = Object.keys(data.errors)[0];
                        if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
                            msg = data.errors[firstKey][0];
                        }
                    }
                    throw new Error(msg);
                }

                this.paymentUuid = data.payment_uuid;
                this.step = 'waiting';
                this.startPolling();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.isInitiating = false;
            }
        },

        startPolling() {
            this.elapsedSeconds = 0;
            this.isTimedOut = false;

            // Timer display
            this.timerInterval = setInterval(() => {
                this.elapsedSeconds++;
            }, 1000);

            // Bounded polling loop:
            // 0-60s: every 2.5s
            // 60-180s: every 5s
            // >180s: stop polling, show graceful reassurance message
            const poll = async () => {
                if (this.step !== 'waiting') return;

                if (this.elapsedSeconds >= 180) {
                    clearInterval(this.timerInterval);
                    this.isTimedOut = true;
                    return;
                }

                try {
                    const res = await fetch('/studio/billing/check/' + this.paymentUuid);
                    const result = await res.json();

                    if (result.is_completed) {
                        clearInterval(this.timerInterval);
                        this.step = 'success';

                        // Broadcast upgrade completion to other open windows/tabs (e.g. Gallery upload modal)
                        try {
                            const bc = new BroadcastChannel('ifotoset_billing');
                            bc.postMessage({ status: 'completed', plan: this.planSlug, timestamp: Date.now() });
                        } catch (_) {}
                        try {
                            localStorage.setItem('ifotoset_billing_completed', JSON.stringify({
                                status: 'completed',
                                plan: this.planSlug,
                                timestamp: Date.now()
                            }));
                        } catch (_) {}
                        return;
                    } else if (result.is_failed) {
                        clearInterval(this.timerInterval);
                        this.step = 'input';
                        this.errorMessage = 'Payment was not completed. Please check your phone or balance and try again.';
                        return;
                    }
                } catch (e) {
                    // Ignore transient network blip in poll
                }

                const nextDelay = this.elapsedSeconds < 60 ? 2500 : 5000;
                setTimeout(poll, nextDelay);
            };

            setTimeout(poll, 2500);
        }
    };
}
</script>
@endsection
