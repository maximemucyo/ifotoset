{{-- Footer shared by the portfolio, About, Contact and booking pages. Vars: $photographer --}}
@php($footerSocial = $photographer->portfolio()->socialLinks())
<footer class="mt-auto border-t border-border/70">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-4 py-10 text-xs text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
        <div class="flex flex-col items-center gap-3 sm:flex-row sm:gap-5">
            <p>&copy; {{ date('Y') }} {{ $photographer->name }}</p>
            <nav class="flex items-center gap-4" aria-label="Footer">
                <a href="{{ $photographer->public_url }}/about" class="transition hover:text-foreground">About</a>
                <a href="{{ $photographer->public_url }}/contact" class="transition hover:text-foreground">Contact</a>
            </nav>
        </div>
        <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
            @if($footerSocial)
                <div class="flex items-center gap-1">
                    @foreach($footerSocial as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $social['label'] }}"
                           class="flex size-8 items-center justify-center rounded-full transition hover:bg-muted hover:text-foreground">
                            <x-icon :name="$social['icon']" class="size-4" />
                        </a>
                    @endforeach
                </div>
            @endif
            @if($photographer->isFree())
                <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'profile', 'utm_medium' => 'footer_logo', 'utm_campaign' => 'powered_by']) }}" class="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
                    <span>Powered by</span> <x-brand.logo size="sm" />
                </a>
            @endif
        </div>
    </div>
</footer>
