<section class="py-20 md:py-28 px-4 sm:px-6 lg:px-8 max-w-[1440px] mx-auto overflow-hidden border-t border-border/80 pb-28">
    <div class="relative bg-gradient-to-r from-primary to-accent text-primary-foreground rounded-3xl p-10 md:p-16 text-center shadow-xl overflow-hidden group">
        <!-- Animated Background Accent Orbs -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute -top-12 -right-12 w-64 h-64 bg-background rounded-full mix-blend-screen filter blur-xl group-hover:scale-110 transition-transform duration-700"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 bg-background rounded-full mix-blend-screen filter blur-xl group-hover:scale-110 transition-transform duration-700"></div>
        </div>

        <div class="relative max-w-2xl mx-auto flex flex-col items-center">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight mb-6 leading-tight text-balance">
                Ready to elevate your photography business?
            </h2>
            <p class="text-lg sm:text-xl text-primary-foreground/90 mb-10 text-balance leading-relaxed">
                Start delivering premium galleries, book more clients, and showcase your best work.
            </p>
            <a
                href="{{ route('register') }}"
                class="px-8 py-4 bg-background text-primary hover:text-accent font-bold rounded-xl transition-all shadow-md hover:shadow-xl hover:scale-[1.03] text-base sm:text-lg"
            >
                Get Started Free
            </a>
        </div>
    </div>
</section>
