<section class="relative min-h-screen pt-32 pb-20 flex items-center justify-center bg-gradient-to-b from-background to-secondary/15 overflow-hidden">
    <!-- Decorative Blur Orbs -->
    <div class="absolute inset-0 opacity-15 dark:opacity-10 pointer-events-none">
        <div class="absolute top-20 right-[-10%] w-96 h-96 bg-primary rounded-full mix-blend-multiply filter blur-3xl"></div>
        <div class="absolute bottom-20 left-[-10%] w-96 h-96 bg-accent rounded-full mix-blend-multiply filter blur-3xl"></div>
    </div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 w-full grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
        <!-- Left Column - Content -->
        <div class="lg:col-span-6 text-center lg:text-left flex flex-col items-center lg:items-start">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight text-foreground leading-[1.1] mb-6 text-balance">
                The Complete Photography Platform for East Africa & Beyond
            </h1>
            <p class="text-lg sm:text-xl text-muted-foreground mb-8 max-w-xl text-balance">
                Everything you need to deliver beautiful galleries, showcase your work, and manage your photography business.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mb-8">
                <a
                    href="{{ route('register') }}"
                    class="px-8 py-4 bg-primary text-primary-foreground text-center rounded-lg font-semibold hover:bg-accent transition-all shadow-md hover:shadow-lg text-lg"
                >
                    Get Started Free
                </a>
                <a
                    href="#showcase"
                    class="px-8 py-4 border border-border bg-card text-foreground hover:bg-secondary text-center rounded-lg font-semibold transition-all text-lg"
                >
                    Explore a Gallery
                </a>
            </div>

            <!-- Trust Row -->
            <div class="flex flex-wrap justify-center lg:justify-start gap-x-6 gap-y-2 text-muted-foreground text-sm font-medium">
                <div class="flex items-center gap-1.5">
                    <span class="text-primary font-bold">✓</span> Free plan available
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-primary font-bold">✓</span> No credit card required
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-primary font-bold">✓</span> Upgrade anytime
                </div>
            </div>
        </div>

        <!-- Right Column - Gallery Mockup -->
        <div class="lg:col-span-6 w-full max-w-xl mx-auto lg:max-w-none flex justify-center">
            <div class="w-full bg-card rounded-2xl border border-border/80 shadow-2xl shadow-primary/5 overflow-hidden transition-all duration-500 hover:border-primary/30">
                <!-- Top Toolbar / Status Bar -->
                <div class="border-b border-border/85 bg-secondary/30 px-6 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Logo and site tag -->
                        <div class="flex items-center gap-2">
                            <img
                                src="{{ asset('logo.png') }}"
                                alt="ifotoset Logo"
                                class="w-6 h-6 object-contain"
                            >
                            <span class="font-bold text-sm tracking-tight text-foreground">
                                ifoto<span class="text-primary">set</span>
                            </span>
                        </div>
                        <span class="text-xs text-muted-foreground border-l border-border pl-3 hidden sm:inline">
                            Client View
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-2.5 h-2.5 rounded-full bg-border"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-border ml-1"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-border ml-1"></div>
                    </div>
                </div>

                <!-- Gallery Cover Block -->
                <div class="relative h-60 w-full overflow-hidden group">
                    <img
                        src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80"
                        alt="Sarah & James Wedding"
                        class="w-full h-full object-cover"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                    <div class="absolute bottom-6 left-6 text-white">
                        <h3 class="text-2xl font-bold tracking-tight">Sarah & James</h3>
                        <p class="text-white/80 text-sm mt-1">Wedding Collection</p>
                    </div>
                </div>

                <!-- Gallery Content Area -->
                <div class="p-6">
                    <!-- Metadata Info & Action Buttons -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/80 pb-5 mb-6">
                        <div class="flex items-center gap-4 text-xs font-semibold text-muted-foreground">
                            <span class="flex items-center gap-1 bg-secondary/50 px-2.5 py-1 rounded">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                420 Photos
                            </span>
                            <span class="flex items-center gap-1 bg-secondary/50 px-2.5 py-1 rounded">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Password Protected
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Download Collection">
                                <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download
                            </button>
                            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Favorite Photo">
                                <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Favorite
                            </button>
                            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Share Collection">
                                <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                                Share
                            </button>
                        </div>
                    </div>

                    <!-- Grid representation -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                                alt="Outdoor couple portrait"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <div class="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=400&q=80"
                                alt="Wedding rings details"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <div class="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                                alt="Bridal portrait details"
                                class="w-full h-full object-cover"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
