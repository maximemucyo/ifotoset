{{--
    "Use my brand" on the Design tab, under Layout: one switch that puts the portfolio's logo, colour and
    title font on the client gallery. Expects $gallery and $previewUrl. Below the switch, the gallery's
    title set the way it will look, so the photographer sees what the switch brings before turning it on.
--}}
@php
    $owner = $gallery->user;
    $portfolio = $owner->portfolio();
    $font = $portfolio->font();
    $brandColor = $portfolio->brandColor();
    $missing = array_keys(array_filter(['logo' => ! $owner->logo_url, 'brand colour' => ! $brandColor]));
    $canEditPortfolio = Gate::allows('studio.profile.manage');
@endphp
@if(in_array($portfolio->fontKey(), ['elegant', 'bold'], true))
    @push('styles')
        @vite('resources/css/portfolio.css')
    @endpush
@endif
<form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" class="card mt-4"
      x-data="settingsForm" @submit.prevent>
    @csrf
    @method('PATCH')
    <div x-data="{ enabled: @js((bool) $gallery->use_branding) }">
        <label class="flex cursor-pointer items-center justify-between gap-4 p-5 sm:px-6">
            <span>
                <span class="block text-[15px] font-semibold text-foreground">Use my brand</span>
                <span class="mt-0.5 block text-[13px] text-muted-foreground"
                      x-text="enabled ? 'On: your logo, colour and title font.' : 'Off: the standard look.'">{{ $gallery->use_branding ? 'On: your logo, colour and title font.' : 'Off: the standard look.' }}</span>
            </span>
            <span class="switch">
                <input type="hidden" name="use_branding" value="0">
                <input type="checkbox" name="use_branding" value="1" class="sr-only" x-model="enabled" @checked($gallery->use_branding)
                       @change="$nextTick(async () => { if (await save(enabled ? 'Brand on' : 'Brand off')) $dispatch('gallery-preview-load', { target: 'docked', url: @js($previewUrl) }) })">
                <span></span>
            </span>
        </label>

        {{-- The title as clients will see it, in the brand's font and with its logo and colour --}}
        <div class="border-t border-border px-5 pb-5 pt-4 sm:px-6">
            <div class="rounded-lg bg-background px-4 py-4 ring-1 ring-border transition-opacity" :class="enabled ? '' : 'opacity-60'">
                @if($owner->logo_url)
                    <img src="{{ $owner->logo_url }}" alt="Your logo" class="h-7 w-auto max-w-[160px] object-contain" decoding="async">
                @else
                    <p class="text-[13px] font-semibold text-foreground">{{ $owner->name }}</p>
                @endif
                <p class="mt-3 line-clamp-2 text-2xl leading-tight text-balance text-foreground"
                   style="font-family: {{ $font['family'] }}; font-weight: {{ $font['weight'] }}; letter-spacing: {{ $font['tracking'] }}">{{ $gallery->title }}</p>
                <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    @if($brandColor)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-3 rounded-full ring-1 ring-black/10" style="background: {{ $brandColor }}"></span>
                            Buttons and hearts
                        </span>
                    @endif
                    <span>Title font: {{ $font['name'] }}</span>
                </p>
            </div>

            @if($missing)
                <p class="mt-3 text-[13px] text-muted-foreground">
                    Your portfolio has no {{ implode(' or ', $missing) }} yet.
                    @if($canEditPortfolio)
                        <a href="{{ route('studio.portfolio.edit') }}#{{ $owner->logo_url ? 'design' : 'branding' }}" class="font-medium text-foreground underline underline-offset-2">Add {{ count($missing) > 1 ? 'them' : 'it' }} in Portfolio</a>
                    @endif
                </p>
            @elseif($canEditPortfolio)
                <p class="mt-3 text-[13px] text-muted-foreground">
                    <a href="{{ route('studio.portfolio.edit') }}#design" class="font-medium text-foreground underline underline-offset-2">Change them in Portfolio</a>
                </p>
            @endif
            @if($owner->isFree())
                <p class="mt-1 text-xs text-muted-foreground">On the Free plan, "Delivered with ifotoset" stays at the bottom of the gallery.</p>
            @endif
        </div>
    </div>
</form>
