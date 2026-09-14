<header
    x-data="{
        scrolled: false,
        isOpen: false,
        isDark: false,
        init() {
            this.isDark = document.documentElement.classList.contains('dark');
        },
        toggleTheme() {
            this.isDark = !this.isDark;
            if (this.isDark) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        }
    }"
    @scroll.window="scrolled = (window.pageYOffset > 50)"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
    :class="scrolled ? 'bg-background/80 backdrop-blur-md border-b border-border py-3 shadow-sm' : 'bg-transparent py-5'"
>
    <nav class="max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <!-- Logo -->
        <a href="{{ url('/') }}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
            <img src="{{ asset('logo.png') }}" alt="ifotoset" class="w-8 h-8 object-contain drop-shadow-sm">
            <span class="text-2xl font-bold tracking-tight text-foreground">
                ifoto<span class="text-primary">set</span>
            </span>
        </a>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Features</a>
            <a href="#showcase" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Showcase</a>
            <a href="#pricing" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Pricing</a>
            <a href="#faq" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">FAQ</a>

            @auth
                <a href="{{ url('/studio/dashboard') }}" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Studio</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-foreground/80 hover:text-primary transition-colors text-sm font-medium">Sign In</a>
                <a href="{{ route('register') }}" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-sm font-semibold shadow-sm">
                    Get Started Free
                </a>
            @endauth

            <!-- Theme Toggle Button -->
            <button
                @click="toggleTheme()"
                type="button"
                class="p-2 rounded-lg hover:bg-secondary transition-colors"
                aria-label="Toggle theme"
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
                <template x-if="isDark">
                    <!-- Sun Icon -->
                    <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </template>
                <template x-if="!isDark">
                    <!-- Moon Icon -->
                    <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </template>
            </button>
        </div>

        <!-- Mobile Navigation Trigger -->
        <div class="md:hidden flex items-center gap-3">
            <button
                @click="toggleTheme()"
                type="button"
                class="p-2 rounded-lg hover:bg-secondary transition-colors"
                aria-label="Toggle theme"
            >
                <template x-if="isDark">
                    <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </template>
                <template x-if="!isDark">
                    <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </template>
            </button>
            <button
                class="p-2 hover:bg-secondary rounded-lg text-foreground focus:outline-none"
                @click="isOpen = !isOpen"
                aria-label="Toggle navigation menu"
                :aria-expanded="isOpen"
            >
                <svg x-show="!isOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="isOpen" style="display: none;" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="absolute top-16 left-0 right-0 bg-background border-b border-border p-5 shadow-lg md:hidden"
            style="display: none;"
        >
            <div class="flex flex-col gap-4">
                <a href="#features" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1">Features</a>
                <a href="#showcase" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1">Showcase</a>
                <a href="#pricing" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1">Pricing</a>
                <a href="#faq" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1">FAQ</a>

                @auth
                    <a href="{{ url('/studio/dashboard') }}" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1 border-t border-border pt-3">Studio</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-left w-full text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @click="isOpen = false" class="text-foreground/80 hover:text-primary transition-colors font-medium text-base py-1 border-t border-border pt-3">Sign In</a>
                    <a href="{{ route('register') }}" @click="isOpen = false" class="w-full px-5 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-center font-semibold shadow-sm">
                        Get Started Free
                    </a>
                @endauth
            </div>
        </div>
    </nav>
</header>
