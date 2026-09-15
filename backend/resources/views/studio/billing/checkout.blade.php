@extends('layouts.app', ['title' => 'Checkout - ' . $plan->name])

@section('content')
<div class="max-w-xl mx-auto space-y-4"
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
         billingUrl: '{{ route('studio.billing.index') }}'
     })">

    <!-- Back Navigation -->
    <div>
        <a href="{{ route('studio.billing.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors">
            &larr; Back to Plans
        </a>
    </div>

    <!-- Step 1: Payment Form View -->
    <div x-show="step === 'input'" class="bg-card border border-border rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-primary">Secure Checkout</span>
            <h1 class="text-2xl font-bold tracking-tight text-foreground mt-1">Upgrade to {{ $plan->name }}</h1>
            <p class="text-xs text-muted-foreground mt-1">Pay with Mobile Money (MTN MoMo or Airtel Money Rwanda).</p>
        </div>

        <!-- Duration Selection -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold text-foreground">
                    Subscription Duration
                </label>
                <span class="text-xs text-muted-foreground" x-text="durationSummary"></span>
            </div>

            <!-- Quick Pill Options -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button type="button"
                        @click="setMonths(1)"
                        :class="months === 1 ? 'border-primary bg-primary/10 text-primary font-bold shadow-sm' : 'border-border bg-secondary/40 text-muted-foreground hover:text-foreground hover:bg-secondary'"
                        class="py-2.5 px-3 rounded-xl border text-xs text-center transition-all">
                    1 Month
                </button>
                <button type="button"
                        @click="setMonths(3)"
                        :class="months === 3 ? 'border-primary bg-primary/10 text-primary font-bold shadow-sm' : 'border-border bg-secondary/40 text-muted-foreground hover:text-foreground hover:bg-secondary'"
                        class="py-2.5 px-3 rounded-xl border text-xs text-center transition-all">
                    3 Months
                </button>
                <button type="button"
                        @click="setMonths(6)"
                        :class="months === 6 ? 'border-primary bg-primary/10 text-primary font-bold shadow-sm' : 'border-border bg-secondary/40 text-muted-foreground hover:text-foreground hover:bg-secondary'"
                        class="py-2.5 px-3 rounded-xl border text-xs text-center transition-all">
                    6 Months
                </button>
                <button type="button"
                        @click="setMonths(12)"
                        :class="months === 12 ? 'border-primary bg-primary/10 text-primary font-bold shadow-sm' : 'border-border bg-secondary/40 text-muted-foreground hover:text-foreground hover:bg-secondary'"
                        class="py-2.5 px-3 rounded-xl border text-xs text-center transition-all relative">
                    <span>12 Months</span>
                    @if($annualPrice > 0 && $annualPrice < ($monthlyPrice * 12))
                        <span class="hidden sm:inline-block ml-1 text-[9px] font-bold text-primary uppercase">Save ~18%</span>
                    @endif
                </button>
            </div>

            <!-- Custom Months Stepper -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-secondary/20 border border-border text-xs">
                <span class="text-muted-foreground">Or choose exact months:</span>
                <div class="flex items-center gap-2.5">
                    <button type="button"
                            @click="decrementMonths()"
                            :disabled="months <= 1"
                            class="w-7 h-7 rounded-lg border border-border bg-card flex items-center justify-center font-bold hover:bg-secondary disabled:opacity-30 disabled:pointer-events-none transition-colors">
                        &minus;
                    </button>
                    <span class="font-mono font-bold text-foreground text-xs min-w-[60px] text-center"
                          x-text="months + (months === 1 ? ' month' : ' months')">
                    </span>
                    <button type="button"
                            @click="incrementMonths()"
                            :disabled="months >= 36"
                            class="w-7 h-7 rounded-lg border border-border bg-card flex items-center justify-center font-bold hover:bg-secondary disabled:opacity-30 disabled:pointer-events-none transition-colors">
                        +
                    </button>
                </div>
            </div>
        </div>

        <!-- Plan Summary Card -->
        <div class="p-4 rounded-2xl bg-secondary/30 border border-border flex items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="text-sm font-bold text-foreground flex items-center gap-1.5 flex-wrap">
                    <span>{{ $plan->name }} Plan</span>
                    <span class="text-muted-foreground font-normal">&bull;</span>
                    <span class="text-primary font-semibold" x-text="durationLabel"></span>
                </div>
                <div class="text-xs text-muted-foreground">
                    @if($plan->slug === 'basic') 50 GB Cloud Storage @elseif($plan->slug === 'pro') 1 TB (1,000 GB) Storage @else 3 TB Storage @endif
                    &bull; Unlimited Galleries
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-extrabold text-primary" x-text="formattedPrice + ' ' + currency">{{ number_format($price, 0) }} {{ $plan->currency }}</div>
                <div class="text-[11px] text-muted-foreground" x-text="billingSubtext">Billed {{ $billingCycle }}</div>
            </div>
        </div>

        <!-- Phone Number & Telecom Selection -->
        <form @submit.prevent="startPayment" class="space-y-6">
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-foreground">
                    Mobile Money Phone Number (Rwanda)
                </label>
                <!-- Responsive Input Group: Flag & Country Code cleanly segmented -->
                <div class="flex items-stretch rounded-xl border border-border bg-input shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all overflow-hidden">
                    <div class="flex items-center gap-1.5 px-3.5 py-3 bg-muted/40 border-r border-border text-xs font-mono font-medium text-foreground select-none shrink-0">
                        <span class="text-base leading-none">🇷🇼</span>
                        <span class="text-muted-foreground font-semibold">+250</span>
                    </div>
                    <input type="tel"
                           x-model="phone"
                           @input="handlePhoneInput"
                           required
                           inputmode="numeric"
                           autocomplete="tel"
                           placeholder="788 000 000"
                           class="flex-1 min-w-0 bg-transparent px-3.5 py-3 text-foreground text-sm font-mono tracking-wide focus:outline-none placeholder:text-muted-foreground/40">
                </div>
                
                <!-- Telecom Network Detection Badge -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <span class="text-muted-foreground">Network:</span>
                    <span x-show="detectedProvider === 'MTN_MOMO_RWA'" class="font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> MTN Mobile Money
                    </span>
                    <span x-show="detectedProvider === 'AIRTEL_RWA'" class="font-bold text-red-600 dark:text-red-400 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span> Airtel Money
                    </span>
                    <span x-show="!detectedProvider" class="text-muted-foreground italic">
                        Auto-detected from phone number
                    </span>
                </div>
            </div>

            <!-- Error message if any -->
            <div x-show="errorMessage" class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-xs text-destructive font-medium" x-text="errorMessage"></div>

            <!-- Submit Button -->
            <button type="submit"
                    :disabled="isInitiating"
                    class="w-full py-3.5 px-6 rounded-xl text-sm font-bold bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-md flex items-center justify-center gap-2 disabled:opacity-50">
                <span x-show="!isInitiating" x-text="'Pay ' + formattedPrice + ' ' + currency">Pay {{ number_format($price, 0) }} {{ $plan->currency }}</span>
                <span x-show="isInitiating" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Sending payment prompt to your phone...
                </span>
            </button>
        </form>

        <!-- Trust and Security Footnote (Clean, friendly, no jargon) -->
        <div class="pt-2 flex items-center justify-center gap-2 text-xs text-muted-foreground text-center">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span>Safe and secure payment via MTN Mobile Money or Airtel Money</span>
        </div>
    </div>

    <!-- Step 2: Waiting / Authorization View -->
    <div x-show="step === 'waiting'" style="display: none;" class="bg-card border border-border rounded-3xl p-5 sm:p-6 shadow-sm space-y-3.5 text-center">
        <!-- Compact Header: Status Badge + Live Ping + Title -->
        <div class="space-y-1.5">
            <div class="flex items-center justify-center gap-2">
                <div class="relative w-7 h-7 rounded-full bg-primary/15 text-primary flex items-center justify-center shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary/30"></span>
                    <svg class="w-3.5 h-3.5 animate-pulse relative" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                    <span>Awaiting Payment Authorization</span>
                </div>
            </div>

            <div>
                <h2 class="text-lg sm:text-xl font-bold tracking-tight text-foreground">Approve the payment on your phone</h2>
                <p class="text-[11px] text-muted-foreground mt-0.5">
                    Check your phone for the Mobile Money prompt to authorize the transaction.
                </p>
            </div>
        </div>

        <!-- Streamlined Transaction Summary Capsule (Compact 1-row layout) -->
        <div class="p-3 sm:p-3.5 rounded-2xl bg-secondary/30 border border-border flex items-center justify-between gap-4 text-left">
            <div>
                <div class="text-[11px] text-muted-foreground">Amount to Pay</div>
                <div class="text-lg sm:text-xl font-black text-primary font-mono leading-tight" x-text="formattedPrice + ' ' + currency"></div>
                <div class="text-[11px] text-muted-foreground mt-0.5" x-text="planName + ' Plan &bull; ' + durationLabel"></div>
            </div>
            <div class="text-right">
                <div class="text-[11px] text-muted-foreground">Recipient Phone</div>
                <div class="font-mono font-bold text-foreground text-xs sm:text-sm leading-tight" x-text="formattedDisplayPhone"></div>
                <div class="pt-0.5">
                    <span x-show="detectedProvider === 'MTN_MOMO_RWA'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold text-[10px] border border-amber-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> MTN MoMo
                    </span>
                    <span x-show="detectedProvider === 'AIRTEL_RWA'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500/15 text-red-700 dark:text-red-300 font-semibold text-[10px] border border-red-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Airtel Money
                    </span>
                </div>
            </div>
        </div>

        <!-- Compact USSD Manual Approval Guide -->
        <div class="p-3 sm:p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-left space-y-2">
            <div class="text-xs font-bold text-foreground flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <span>Didn't receive the prompt on your phone?</span>
            </div>

            <!-- 3-step Quick Manual Guide -->
            <div class="text-xs text-muted-foreground bg-background/70 rounded-xl p-2.5 border border-border/50 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 flex items-center justify-center font-bold text-[10px] shrink-0">1</span>
                    <span class="text-foreground">Dial <a href="tel:*182*7*1%23" class="font-mono font-bold text-amber-600 dark:text-amber-400 hover:underline" title="Click to dial">*182*7*1#</a> on your phone</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 flex items-center justify-center font-bold text-[10px] shrink-0">2</span>
                    <span class="text-foreground">Enter your Mobile Money PIN</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 flex items-center justify-center font-bold text-[10px] shrink-0">3</span>
                    <span class="text-foreground">Select the pending payment to confirm</span>
                </div>
            </div>
        </div>

        <!-- Realtime Status Ticker & Edit Number (Single compact row) -->
        <div class="flex items-center justify-center flex-wrap gap-2 text-xs text-muted-foreground pt-0.5">
            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 animate-spin text-primary shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Waiting for confirmation... (<strong class="font-mono text-foreground" x-text="elapsedSeconds + 's'"></strong>)</span>
            </div>
            <span class="text-muted-foreground/40 hidden sm:inline">&bull;</span>
            <div>
                Wrong number?
                <button type="button" @click="cancelWaiting()" class="text-primary hover:underline font-semibold ml-0.5">
                    Change phone number
                </button>
            </div>
        </div>

        <!-- Graceful Timeout Notice -->
        <div x-show="isTimedOut" class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-left space-y-1.5">
            <div class="font-bold text-amber-700 dark:text-amber-300">Still waiting for confirmation</div>
            <p class="text-muted-foreground leading-relaxed text-[11px]">
                If you already entered your PIN, you can safely leave this page &mdash; your account will activate automatically as soon as confirmation is received.
            </p>
            <div class="pt-0.5">
                <a :href="billingUrl" class="inline-block px-3 py-1.5 rounded-lg bg-secondary font-bold text-foreground text-xs hover:bg-secondary/80 transition-colors">
                    View Billing &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Step 3: Success Celebration View -->
    <div x-show="step === 'success'" style="display: none;" class="bg-card border border-border rounded-3xl p-6 sm:p-8 shadow-sm space-y-6 text-center">
        <div class="w-16 h-16 rounded-full bg-green-500/10 text-green-500 mx-auto flex items-center justify-center text-3xl font-bold">
            &check;
        </div>

        <div class="space-y-2">
            <h2 class="text-2xl font-bold text-foreground">Payment Successful!</h2>
            <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                Your account has been upgraded to <strong class="text-foreground">{{ $plan->name }}</strong> for <span class="font-semibold" x-text="durationLabel"></span>. Your new storage quota and features are active immediately.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-4">
            <a :href="'/studio/billing/receipt/' + paymentUuid" class="w-full sm:w-auto py-2.5 px-5 rounded-xl text-xs font-bold bg-secondary text-foreground hover:bg-secondary/80 transition-colors">
                View Receipt
            </a>
            <a href="{{ route('studio.dashboard') }}" class="w-full sm:w-auto py-2.5 px-5 rounded-xl text-xs font-bold bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                Go to Studio Dashboard &rarr;
            </a>
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
            if (this.months === 1) return '1 Month';
            if (this.months === 12) return '12 Months (1 Year)';
            return `${this.months} Months`;
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
