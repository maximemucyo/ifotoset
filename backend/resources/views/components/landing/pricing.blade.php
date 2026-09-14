<section id="pricing" x-data="{ billingPeriod: 'yearly' }" class="py-20 md:py-28 px-4 sm:px-6 lg:px-8 max-w-[1440px] mx-auto overflow-hidden border-t border-border/80">
    <div class="text-center max-w-3xl mx-auto mb-12">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
            Simple Pricing. No Surprises.
        </h2>
        <p class="text-lg text-muted-foreground">
            Choose the billing plan that scales with your photography workspace. Start free, upgrade anytime.
        </p>
    </div>

    <!-- Billing Period Toggle -->
    <div class="flex flex-col sm:flex-row justify-center items-center gap-4 mb-16">
        <div class="relative flex items-center p-1 bg-muted/40 rounded-full border border-border/60 max-w-fit shadow-inner">
            <button
                type="button"
                @click="billingPeriod = 'monthly'"
                class="relative z-10 px-6 py-2 text-sm font-semibold rounded-full transition-all duration-300 cursor-pointer"
                :class="billingPeriod === 'monthly' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:text-foreground'"
            >
                Billed Monthly
            </button>
            <button
                type="button"
                @click="billingPeriod = 'yearly'"
                class="relative z-10 px-6 py-2 text-sm font-semibold rounded-full transition-all duration-300 cursor-pointer"
                :class="billingPeriod === 'yearly' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:text-foreground'"
            >
                Billed Yearly
            </button>
        </div>
        <span class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold text-primary bg-primary/10 border border-primary/20 animate-pulse sm:-translate-y-0">
            Save up to 18%
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 max-w-[1440px] mx-auto">
        <!-- Free Plan -->
        <div class="relative rounded-2xl p-8 border border-border bg-card hover:border-primary/45 shadow-sm transition-all duration-300 flex flex-col justify-between">
            <div>
                <h3 class="text-2xl font-bold text-foreground mb-1">
                    Free
                </h3>
                <p class="text-primary font-semibold text-xs tracking-wide uppercase mb-3">
                    Learn the platform
                </p>
                <p class="text-muted-foreground text-sm mb-6 min-h-[40px]">
                    Perfect for new photographers starting to establish their presence.
                </p>

                <div class="mb-6 min-h-[68px]">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-4xl font-extrabold tracking-tight text-primary">
                            RWF 0
                        </span>
                        <span class="text-muted-foreground text-sm font-semibold">
                            /month
                        </span>
                    </div>
                    <div class="text-muted-foreground text-xs font-medium mt-1.5" x-text="billingPeriod === 'yearly' ? 'Billed Never' : 'Billed monthly'">
                        Billed Never
                    </div>
                </div>

                <ul class="space-y-3.5 mb-8 border-t border-border/60 pt-6">
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Portfolio Website</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Unlimited Galleries</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">2 GB Optimized Storage</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Photo galleries only</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Email Support</span>
                    </li>
                </ul>
            </div>

            <div>
                <a
                    href="{{ route('register') }}"
                    class="block w-full py-3 px-4 rounded-xl font-bold text-center transition-all text-sm bg-secondary text-foreground hover:bg-border"
                >
                    Get Started Free
                </a>
            </div>
        </div>

        <!-- Basic Plan -->
        <div class="relative rounded-2xl p-8 border border-border bg-card hover:border-primary/45 shadow-sm transition-all duration-300 flex flex-col justify-between">
            <div>
                <h3 class="text-2xl font-bold text-foreground mb-1">
                    Basic
                </h3>
                <p class="text-primary font-semibold text-xs tracking-wide uppercase mb-3">
                    Start delivering professionally
                </p>
                <p class="text-muted-foreground text-sm mb-6 min-h-[40px]">
                    For growing photographers who need more storage and video tools.
                </p>

                <div class="mb-6 min-h-[68px]">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-4xl font-extrabold tracking-tight text-primary" x-text="billingPeriod === 'monthly' ? 'RWF 10,999' : 'RWF 8,999'">
                            RWF 8,999
                        </span>
                        <span class="text-muted-foreground text-sm font-semibold">
                            /month
                        </span>
                    </div>
                    <div class="text-muted-foreground text-xs font-medium mt-1.5" x-text="billingPeriod === 'yearly' ? 'Billed annually (RWF 107,988/year)' : 'Billed monthly'">
                        Billed annually (RWF 107,988/year)
                    </div>
                </div>

                <ul class="space-y-3.5 mb-8 border-t border-border/60 pt-6">
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Portfolio Website</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Unlimited Galleries</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">50 GB Optimized Storage</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Up to 30 minutes of hosted video</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Email Support</span>
                    </li>
                </ul>
            </div>

            <div>
                <a
                    href="{{ route('register') }}"
                    class="block w-full py-3 px-4 rounded-xl font-bold text-center transition-all text-sm bg-secondary text-foreground hover:bg-border"
                >
                    Choose Basic
                </a>
            </div>
        </div>

        <!-- Professional Plan (Highlighted) -->
        <div class="relative rounded-2xl p-8 border border-primary bg-card xl:scale-105 shadow-xl shadow-primary/5 ring-1 ring-primary z-10 transition-all duration-300 flex flex-col justify-between">
            <span class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-primary text-primary-foreground text-xs font-bold uppercase tracking-wider rounded-full shadow">
                Most Popular
            </span>

            <div>
                <h3 class="text-2xl font-bold text-foreground mb-1">
                    Professional
                </h3>
                <p class="text-primary font-semibold text-xs tracking-wide uppercase mb-3">
                    Run your photography business
                </p>
                <p class="text-muted-foreground text-sm mb-6 min-h-[40px]">
                    For established photographers running a full-time business.
                </p>

                <div class="mb-6 min-h-[68px]">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-4xl font-extrabold tracking-tight text-primary" x-text="billingPeriod === 'monthly' ? 'RWF 29,999' : 'RWF 24,999'">
                            RWF 24,999
                        </span>
                        <span class="text-muted-foreground text-sm font-semibold">
                            /month
                        </span>
                    </div>
                    <div class="text-muted-foreground text-xs font-medium mt-1.5" x-text="billingPeriod === 'yearly' ? 'Billed annually (RWF 299,988/year)' : 'Billed monthly'">
                        Billed annually (RWF 299,988/year)
                    </div>
                </div>

                <ul class="space-y-3.5 mb-8 border-t border-border/60 pt-6">
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Everything in Basic</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">1 TB Optimized Storage</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Up to 5 hours of hosted video</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Booking Manager</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Secure Payments integration</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Custom Domain Support</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Priority Support</span>
                    </li>
                </ul>
            </div>

            <div>
                <a
                    href="{{ route('register') }}"
                    class="block w-full py-3 px-4 rounded-xl font-bold text-center transition-all text-sm bg-primary text-primary-foreground hover:bg-accent shadow-md hover:shadow-lg"
                >
                    Choose Professional
                </a>
            </div>
        </div>

        <!-- Business Plan -->
        <div class="relative rounded-2xl p-8 border border-border bg-card hover:border-primary/45 shadow-sm transition-all duration-300 flex flex-col justify-between">
            <div>
                <h3 class="text-2xl font-bold text-foreground mb-1">
                    Business
                </h3>
                <p class="text-primary font-semibold text-xs tracking-wide uppercase mb-3">
                    Scale your studio
                </p>
                <p class="text-muted-foreground text-sm mb-6 min-h-[40px]">
                    For agencies, studios, and teams scaling their operations.
                </p>

                <div class="mb-6 min-h-[68px]">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-4xl font-extrabold tracking-tight text-primary" x-text="billingPeriod === 'monthly' ? 'RWF 59,999' : 'RWF 49,999'">
                            RWF 49,999
                        </span>
                        <span class="text-muted-foreground text-sm font-semibold">
                            /month
                        </span>
                    </div>
                    <div class="text-muted-foreground text-xs font-medium mt-1.5" x-text="billingPeriod === 'yearly' ? 'Billed annually (RWF 599,988/year)' : 'Billed monthly'">
                        Billed annually (RWF 599,988/year)
                    </div>
                </div>

                <ul class="space-y-3.5 mb-8 border-t border-border/60 pt-6">
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Everything in Professional</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">3 TB Optimized Storage</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Up to 15 hours of hosted video</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Multi-user team accounts</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Dedicated setup & onboarding</span>
                    </li>
                    <li class="flex items-start gap-3 text-sm text-foreground">
                        <span class="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold mt-0.5">✓</span>
                        <span class="leading-tight">Priority Support</span>
                    </li>
                </ul>
            </div>

            <div>
                <a
                    href="{{ route('register') }}"
                    class="block w-full py-3 px-4 rounded-xl font-bold text-center transition-all text-sm bg-secondary text-foreground hover:bg-border"
                >
                    Choose Business
                </a>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-muted-foreground mt-12 max-w-2xl mx-auto leading-relaxed">
        * Storage limits and video hosting durations are optimized for premium client delivery. Large uploads are transcoded automatically. Storage and video limits are subject to our <a href="#" class="underline hover:text-primary transition-colors">Fair Use Policy</a>.
    </p>
</section>
