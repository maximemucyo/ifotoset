<footer class="bg-card border-t border-border py-12">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
            <div class="col-span-2 md:col-span-1">
                <a href="{{ url('/') }}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <img src="{{ asset('logo.png') }}" alt="ifotoset" class="w-8 h-8 object-contain drop-shadow-sm">
                    <span class="text-2xl font-bold tracking-tight text-foreground">
                        ifoto<span class="text-primary">set</span>
                    </span>
                </a>
                <p class="text-muted-foreground text-sm mt-4">
                    Photography platform built for modern photographers.
                </p>
            </div>
            <div>
                <p class="font-semibold text-foreground mb-4">Product</p>
                <ul class="space-y-2 text-muted-foreground text-sm">
                    <li><a href="#features" class="hover:text-primary transition-colors">Features</a></li>
                    <li><a href="#pricing" class="hover:text-primary transition-colors">Pricing</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Security</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-foreground mb-4">Company</p>
                <ul class="space-y-2 text-muted-foreground text-sm">
                    <li><a href="#" class="hover:text-primary transition-colors">About</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Blog</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Contact</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-foreground mb-4">Legal</p>
                <ul class="space-y-2 text-muted-foreground text-sm">
                    <li><a href="#" class="hover:text-primary transition-colors">Privacy</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Terms</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-border pt-8 text-center text-muted-foreground text-sm">
            <p>&copy; {{ date('Y') }} ifotoset. All rights reserved.</p>
        </div>
    </div>
</footer>
