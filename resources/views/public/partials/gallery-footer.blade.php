<footer class="mt-auto select-none border-t border-border/70">
    <div class="flex w-full flex-col items-center justify-center gap-3 px-4 py-10 text-xs text-muted-foreground sm:flex-row sm:justify-between sm:px-6 lg:px-10">
        <a href="{{ $photographer->public_url }}" class="font-serif text-lg tracking-normal text-foreground transition-opacity hover:opacity-70">
            @if($gallery->brand() && $photographer->logo_url)
                <x-gallery.logo :photographer="$photographer" class="h-8 max-w-[200px]" />
            @else
                {{ $photographer->name }}
            @endif
        </a>
        @if($photographer->isFree())
            <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'gallery', 'utm_medium' => 'footer_logo', 'utm_campaign' => 'powered_by']) }}"
               class="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
                <span>Delivered with</span>
                <x-brand.logo size="sm" />
            </a>
        @else
            <p>&copy; {{ date('Y') }} {{ $photographer->name }}. All rights reserved.</p>
        @endif
    </div>
</footer>
