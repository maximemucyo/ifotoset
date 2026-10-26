@props([
    'photo',
    'allowDownloads' => true,
    // Presigned original links bypass the download route; off while downloads are disabled or PIN-locked
    'withOriginals' => true,
    'eager' => false,
    // "{gallery title} – photo by {photographer}" when the photo has no title or caption
    'altFallback' => '',
])

@php
    $isVideo = $photo->isVideo();
    $fullUrl = $photo->getUrl('xl');
    $largeUrl = $photo->getUrl('lg');
    $thumbnailUrl = $photo->getThumbnailUrl('md');
    $originalUrl = $withOriginals ? $photo->getOriginalDownloadUrl() : '';
    $deliveryUrl = $isVideo ? $photo->getStreamUrl() : null;
    $deliveryDownloadUrl = $withOriginals ? ($isVideo ? $photo->getDeliveryDownloadUrl() : $originalUrl) : '';
    $srcset = $photo->getSrcset();
    $blurhash = $photo->blurhash;
    $w = $photo->width ?: 1920;
    $h = $photo->height ?: 1080;
@endphp

{{-- Markup mirrored in resources/js/gallery/modules/pagination.js (createCardElement). Keep both in sync. --}}
<div class="photo-card group relative cursor-pointer select-none overflow-hidden bg-muted"
     style="aspect-ratio: {{ $w }} / {{ $h }};"
     data-photo-uuid="{{ $photo->uuid }}"
     data-photo-filename="{{ $photo->original_filename }}"
     data-photo-large="{{ $largeUrl }}"
     data-photo-full="{{ $fullUrl }}"
     data-photo-original="{{ $originalUrl }}"
     data-photo-delivery="{{ $deliveryUrl }}"
     data-photo-delivery-download="{{ $deliveryDownloadUrl }}"
     data-photo-thumb="{{ $thumbnailUrl }}"
     data-photo-srcset="{{ $srcset }}"
     data-photo-blurhash="{{ $blurhash }}"
     data-photo-width="{{ $w }}"
     data-photo-height="{{ $h }}"
     data-is-video="{{ $isVideo ? 'true' : 'false' }}"
     data-video-duration="{{ $photo->duration_formatted }}">

    {{-- The whole image is one real button; the actions below are siblings, never nested inside it --}}
    <button type="button"
            class="photo-card-open absolute inset-0 z-[1] size-full cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white"
            aria-label="View {{ $isVideo ? 'video' : 'photo' }} {{ $photo->original_filename }}"></button>

    {{-- Blurhash placeholder --}}
    <canvas class="photo-blurhash-canvas pointer-events-none absolute inset-0 size-full transition-opacity duration-700"></canvas>

    {{-- Image (poster for video) --}}
    <img src="{{ $thumbnailUrl }}"
         @if($srcset) srcset="{{ $srcset }}" sizes="(max-width: 639px) 50vw, (max-width: 1023px) 33vw, (max-width: 1699px) 25vw, 20vw" @endif
         alt="{{ $photo->title ?: ($photo->caption ?: $altFallback) }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}"
         @if($eager) fetchpriority="high" @endif
         decoding="async"
         width="{{ $w }}"
         height="{{ $h }}"
         class="pointer-events-none relative size-full object-cover opacity-0 transition-[opacity,transform] duration-700 ease-out group-hover:scale-[1.025]"
         onload="this.classList.remove('opacity-0'); if (this.previousElementSibling) this.previousElementSibling.classList.add('opacity-0');">

    @if($isVideo)
        <div class="video-duration-badge pointer-events-none absolute bottom-2.5 left-2.5 z-[3] inline-flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur-md">
            <x-icon name="play" class="size-3 fill-current" />
            <span class="tabular-nums">{{ $photo->duration_formatted ?: 'Video' }}</span>
        </div>
    @endif

    {{-- Persistent favourite marker --}}
    <div class="favorite-badge pointer-events-none absolute right-2.5 top-2.5 z-[3] hidden text-white drop-shadow-[0_1px_4px_rgb(0_0_0/0.5)] transition-opacity duration-300 group-hover:opacity-0"
         data-badge-uuid="{{ $photo->uuid }}">
        <x-icon name="heart" class="size-[18px] fill-current" />
    </div>

    {{-- Hover actions --}}
    <div class="pointer-events-none absolute inset-0 z-[2] flex items-end justify-end bg-gradient-to-t from-black/50 via-black/0 to-black/0 p-2 opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100">
        <div class="pointer-events-auto flex items-center gap-0.5">
            <button type="button"
                    class="btn-favorite grid size-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                    data-uuid="{{ $photo->uuid }}"
                    aria-label="Favorite photo {{ $photo->original_filename }}"
                    title="Favorite">
                <x-icon name="heart" class="size-[18px]" />
            </button>
            <button type="button"
                    class="btn-share-photo grid size-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                    data-uuid="{{ $photo->uuid }}"
                    data-filename="{{ $photo->original_filename }}"
                    aria-label="Share photo"
                    title="Share">
                <x-icon name="share" class="size-[18px]" />
            </button>
            @if($allowDownloads)
                <button type="button"
                        class="btn-download-photo grid size-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                        data-uuid="{{ $photo->uuid }}"
                        data-filename="{{ $photo->original_filename }}"
                        data-url="{{ $deliveryDownloadUrl }}"
                        data-original-url="{{ $originalUrl }}"
                        aria-label="Download {{ $isVideo ? 'video' : 'photo' }}"
                        title="Download">
                    <x-icon name="download" class="size-[18px]" />
                </button>
            @endif
        </div>
    </div>
</div>
