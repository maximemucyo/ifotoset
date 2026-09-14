<section id="showcase" x-data="{ activeTab: 'galleries' }" class="py-20 md:py-28 px-4 sm:px-6 lg:px-8 max-w-[1440px] mx-auto overflow-hidden border-t border-border/80">
    <div class="text-center max-w-3xl mx-auto mb-12">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
            See the Product in Action
        </h2>
        <p class="text-lg text-muted-foreground">
            Delivering files, showing off your best work, and closing client bookings has never been so seamless.
        </p>
    </div>

    <!-- Tab controls -->
    <div class="flex justify-center border-b border-border mb-12">
        <div role="tablist" aria-label="ifotoset Product Showcase" class="flex gap-2 sm:gap-6">
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'galleries'"
                @click="activeTab = 'galleries'"
                class="py-4 px-4 sm:px-6 font-semibold text-sm sm:text-base border-b-2 transition-all outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-t-lg"
                :class="activeTab === 'galleries' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                Client Galleries
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'portfolio'"
                @click="activeTab = 'portfolio'"
                class="py-4 px-4 sm:px-6 font-semibold text-sm sm:text-base border-b-2 transition-all outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-t-lg"
                :class="activeTab === 'portfolio' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                Portfolio Showcase
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'studio'"
                @click="activeTab = 'studio'"
                class="py-4 px-4 sm:px-6 font-semibold text-sm sm:text-base border-b-2 transition-all outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-t-lg"
                :class="activeTab === 'studio' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                Studio Manager
            </button>
        </div>
    </div>

    <!-- Showcase Grid (Dynamic Layout) -->
    <div class="grid lg:grid-cols-12 gap-12 items-center min-h-[480px]">
        <!-- Left Side: Dynamic Copy -->
        <div class="lg:col-span-5 flex flex-col justify-center text-center lg:text-left">
            <!-- Galleries Text -->
            <div x-show="activeTab === 'galleries'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <span class="text-xs uppercase tracking-widest text-primary font-bold mb-2 block">
                    Client Galleries
                </span>
                <h3 class="text-2xl sm:text-3xl font-bold text-foreground mb-4">
                    Beautiful galleries your clients will love.
                </h3>
                <p class="text-muted-foreground leading-relaxed text-base">
                    Deliver their photos through a fast, branded experience designed around your work. Give clients the options to download, share, and favorite their photos seamlessly.
                </p>
            </div>

            <!-- Portfolio Text -->
            <div x-show="activeTab === 'portfolio'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                <span class="text-xs uppercase tracking-widest text-primary font-bold mb-2 block">
                    Portfolio Showcase
                </span>
                <h3 class="text-2xl sm:text-3xl font-bold text-foreground mb-4">
                    Turn your photography into your best marketing tool.
                </h3>
                <p class="text-muted-foreground leading-relaxed text-base">
                    Create a clean, stunning public profile that displays your editorial collections. Let prospects explore your portfolios and book inquiries directly from your website.
                </p>
            </div>

            <!-- Studio Text -->
            <div x-show="activeTab === 'studio'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                <span class="text-xs uppercase tracking-widest text-primary font-bold mb-2 block">
                    Studio Manager
                </span>
                <h3 class="text-2xl sm:text-3xl font-bold text-foreground mb-4">
                    Spend less time managing and more time creating.
                </h3>
                <p class="text-muted-foreground leading-relaxed text-base">
                    An all-in-one studio management workspace. Track your booking calendar, package details, client lists, and monthly earnings directly from an interactive panel.
                </p>
            </div>
        </div>

        <!-- Right Side: High-Fidelity Mockups -->
        <div class="lg:col-span-7 w-full flex justify-center">
            <div class="w-full relative aspect-[16/10] bg-card rounded-xl border border-border/80 shadow-xl overflow-hidden transition-all duration-500 hover:border-primary/20">
                <!-- Galleries Mockup -->
                <div x-show="activeTab === 'galleries'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full h-full p-4 flex flex-col justify-between bg-card text-foreground">
                    <!-- Galleries Header -->
                    <div class="flex items-center justify-between border-b border-border/80 pb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-xs">SJ</div>
                            <div>
                                <h4 class="font-bold text-xs">Sarah Jenkins</h4>
                                <p class="text-[10px] text-muted-foreground">Kigali, Rwanda</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-2 py-1 bg-secondary text-muted-foreground text-[10px] rounded font-semibold flex items-center gap-1">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Private
                            </span>
                        </div>
                    </div>

                    <!-- Galleries Body Grid -->
                    <div class="flex-1 py-4 grid grid-cols-3 gap-2 overflow-hidden">
                        <div class="relative rounded overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=400&q=80"
                                alt="Gallery Photo 1"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <div class="relative rounded overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                                alt="Gallery Photo 2"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <div class="relative rounded overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                                alt="Gallery Photo 3"
                                class="w-full h-full object-cover"
                            >
                        </div>
                    </div>

                    <!-- Galleries Footer Controls -->
                    <div class="border-t border-border/80 pt-3 flex items-center justify-between text-xs text-muted-foreground">
                        <span>Wedding - Sarah & John</span>
                        <div class="flex gap-3">
                            <button type="button" class="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                Favorite
                            </button>
                            <button type="button" class="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Download
                            </button>
                            <button type="button" class="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                Share
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Portfolio Mockup -->
                <div x-show="activeTab === 'portfolio'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full h-full p-4 flex flex-col justify-between bg-card text-foreground" style="display: none;">
                    <!-- Portfolio Branding -->
                    <div class="flex items-center justify-between border-b border-border/80 pb-3">
                        <span class="font-bold text-xs uppercase tracking-wider">SARAH JENKINS</span>
                        <div class="flex gap-4 text-[10px] font-medium text-muted-foreground">
                            <span class="text-primary font-semibold">Galleries</span>
                            <span>About</span>
                            <span>Contact</span>
                        </div>
                    </div>

                    <!-- Portfolio Collage -->
                    <div class="flex-1 py-4 grid grid-cols-12 gap-2 overflow-hidden">
                        <div class="col-span-8 relative rounded overflow-hidden border border-border/50">
                            <img
                                src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                                alt="Landscape cover"
                                class="w-full h-full object-cover"
                            >
                            <div class="absolute bottom-2 left-2 bg-black/60 backdrop-blur-sm px-2 py-0.5 rounded text-[8px] text-white">East African Landscapes</div>
                        </div>
                        <div class="col-span-4 flex flex-col gap-2">
                            <div class="flex-1 relative rounded overflow-hidden border border-border/50">
                                <img
                                    src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                                    alt="Portrait collection"
                                    class="w-full h-full object-cover"
                                >
                            </div>
                            <div class="flex-1 relative rounded overflow-hidden border border-border/50">
                                <img
                                    src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=400&q=80"
                                    alt="Weddings"
                                    class="w-full h-full object-cover"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Portfolio Info -->
                    <div class="border-t border-border/80 pt-3 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">&copy; {{ date('Y') }} Sarah Jenkins. All rights reserved.</span>
                        <button type="button" class="px-3 py-1 bg-primary text-primary-foreground font-semibold rounded text-[10px] hover:bg-accent transition-colors">Book Inquiry</button>
                    </div>
                </div>

                <!-- Studio Mockup -->
                <div x-show="activeTab === 'studio'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full h-full p-4 flex flex-col bg-secondary/10 text-foreground" style="display: none;">
                    <!-- Studio Bar Header -->
                    <div class="flex items-center justify-between border-b border-border/80 pb-3 mb-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] bg-primary text-primary-foreground font-semibold px-2 py-0.5 rounded">STUDIO</span>
                            <span class="font-bold text-xs">Sarah's Workspace</span>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] text-muted-foreground font-semibold">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span> Live Status
                        </div>
                    </div>

                    <!-- Studio Metrics Row -->
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                            <span class="text-[9px] text-muted-foreground block font-medium">Active Galleries</span>
                            <span class="text-sm font-bold text-foreground mt-0.5">12</span>
                        </div>
                        <div class="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                            <span class="text-[9px] text-muted-foreground block font-medium">Monthly Views</span>
                            <span class="text-sm font-bold text-foreground mt-0.5">2,341</span>
                        </div>
                        <div class="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                            <span class="text-[9px] text-muted-foreground block font-medium">Total Earnings</span>
                            <span class="text-sm font-bold text-primary mt-0.5">RWF 627K</span>
                        </div>
                    </div>

                    <!-- Mini Table List -->
                    <div class="flex-1 bg-card border border-border/80 rounded-lg p-2.5 overflow-hidden flex flex-col">
                        <span class="text-[9px] font-bold text-muted-foreground mb-1.5 uppercase tracking-wider block">Recent Galleries</span>
                        <div class="flex-1 overflow-y-auto space-y-1.5 text-[10px]">
                            <div class="flex items-center justify-between py-1 border-b border-border/40">
                                <span class="font-semibold text-foreground truncate max-w-[120px]">Wedding - Sarah & John</span>
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 text-[8px] rounded font-bold">Published</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-border/40">
                                <span class="font-semibold text-foreground truncate max-w-[120px]">Corporate Tech Summit</span>
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 text-[8px] rounded font-bold">Published</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-border/40">
                                <span class="font-semibold text-foreground truncate max-w-[120px]">Portrait Session - Jan</span>
                                <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300 text-[8px] rounded font-bold">Draft</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
