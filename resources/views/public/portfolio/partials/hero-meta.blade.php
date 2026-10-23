{{-- Location / website / collection count row under the hero text. Vars: $center (bool), $inverse (bool, on a photo) --}}
<div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] {{ ($inverse ?? false) ? 'text-white/80' : 'text-muted-foreground' }} {{ ($center ?? false) ? 'justify-center' : '' }}">
    @if($photographer->location)
        <span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5" /> {{ $photographer->location }}</span>
    @endif
    @if($websiteHost)
        <a href="{{ $photographer->website }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 transition-colors {{ ($inverse ?? false) ? 'hover:text-white' : 'hover:text-foreground' }}">
            <x-icon name="globe" class="size-3.5" /> {{ $websiteHost }}
        </a>
    @endif
    @if($galleries->isNotEmpty())
        <span class="inline-flex items-center gap-1.5"><x-icon name="images" class="size-3.5" /> {{ $galleries->count() }} {{ Str::plural('collection', $galleries->count()) }}</span>
    @endif
</div>
