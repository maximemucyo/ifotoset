{{--
    Server-rendered photo card. Keep in sync with createPhotoCardElement() in
    manager-script.blade.php: both share $gmCardClasses and the same state
    classes/selectors (.photo-card, .is-cover, .is-hidden, .is-selected,
    .cover-badge, .hidden-badge, .hidden-label, .btn-set-cover, .btn-toggle-hide,
    .video-duration-badge, .processing-overlay, .card-badges).
--}}
@php
    $thumbUrl = $photo->getThumbnailUrl('sm') ?: $photo->getUrl('sm');
    $isCover = $gallery->cover_photo_id === $photo->id;
    $isHidden = (bool) $photo->is_hidden;
    $isVideo = $photo->isVideo();
    $isProcessing = $photo->status === 'processing';
    $c = $gmCardClasses;
@endphp
<div id="photo-card-{{ $photo->uuid }}"
     data-uuid="{{ $photo->uuid }}"
     data-id="{{ $photo->id }}"
     class="{{ $c['card'] }} {{ $isCover ? 'is-cover' : '' }} {{ $isHidden ? 'is-hidden' : '' }}">

    <div class="photo-media relative aspect-square cursor-pointer overflow-hidden bg-muted"
         @click="cardClick('{{ $photo->uuid }}')">
        <img src="{{ $thumbUrl }}"
             alt="{{ $photo->original_filename }}"
             loading="lazy"
             decoding="async"
             onerror="this.style.visibility='hidden'"
             class="{{ $c['img'] }}" />

        <div class="{{ $c['scrim'] }}"></div>

        <!-- Select -->
        <button type="button"
                @click.stop="toggleSelect('{{ $photo->uuid }}')"
                class="photo-select {{ $c['select'] }}"
                aria-label="Select {{ $photo->original_filename }}">
            <x-icon name="check" class="size-3.5" stroke-width="3" />
        </button>

        <!-- Hover Actions -->
        <div class="{{ $c['actions'] }}" @click.stop>
            <button type="button"
                    @click="setCover('{{ $photo->uuid }}')"
                    class="btn-set-cover {{ $c['actionBtn'] }} {{ ($isCover || $isHidden) ? 'hidden' : '' }}"
                    title="Set as cover" aria-label="Set as cover">
                <x-icon name="star" class="size-4" />
            </button>
            <button type="button"
                    @click="toggleHide('{{ $photo->uuid }}')"
                    class="btn-toggle-hide {{ $c['actionBtn'] }} group-[.is-hidden]:bg-warning group-[.is-hidden]:hover:bg-warning/90"
                    title="{{ $isHidden ? 'Show in client gallery' : 'Hide from client gallery' }}"
                    aria-label="Toggle visibility">
                <span class="contents group-[.is-hidden]:hidden"><x-icon name="eye-off" class="size-4" /></span>
                <span class="hidden group-[.is-hidden]:contents"><x-icon name="eye" class="size-4" /></span>
            </button>
            <button type="button"
                    @click="deletePhoto('{{ $photo->uuid }}')"
                    class="{{ $c['actionBtn'] }} hover:!bg-destructive"
                    title="Delete" aria-label="Delete">
                <x-icon name="trash-2" class="size-4" />
            </button>
        </div>

        <!-- Badges -->
        <div class="card-badges pointer-events-none absolute bottom-2 left-2 z-10 flex flex-wrap items-center gap-1">
            <span class="cover-badge {{ $c['badgeLight'] }} {{ $isCover ? '' : 'hidden' }}">
                <x-icon name="star" class="size-3 fill-current" /> Cover
            </span>
            <span class="hidden-badge {{ $c['badgeDark'] }} {{ $isHidden ? '' : 'hidden' }}">
                <x-icon name="eye-off" class="size-3" /> Hidden
            </span>
            @if($isVideo)
                <span class="video-duration-badge {{ $c['badgeDark'] }}">
                    <x-icon name="play" class="size-3 fill-current" />
                    <span>{{ $photo->duration_formatted ?: 'Video' }}</span>
                </span>
            @endif
        </div>

        @if($isProcessing)
            <div class="processing-overlay pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-muted/70">
                <div class="skeleton absolute inset-0 !rounded-none opacity-70"></div>
                <span class="relative inline-flex items-center gap-1.5 rounded-full bg-card/95 px-2.5 py-1 text-[11px] font-medium text-foreground shadow-sm ring-1 ring-border">
                    <x-icon name="loader-circle" class="size-3.5 animate-spin" /> Processing
                </span>
            </div>
        @endif
    </div>

    <div class="{{ $c['footer'] }}">
        <span class="truncate" title="{{ $photo->original_filename }}">{{ $photo->original_filename }}</span>
        <span class="flex shrink-0 items-center gap-1.5">
            <span class="hidden-label text-warning {{ $isHidden ? '' : 'hidden' }}" title="Hidden from client gallery">
                <x-icon name="eye-off" class="size-3" />
            </span>
            @if($photo->size)
                @php($sz = (int) $photo->size)
                <span class="font-mono text-[10px] text-subtle">{{ $sz >= 1048576 ? round($sz / 1048576, 1) . ' MB' : ($sz >= 1024 ? round($sz / 1024, 1) . ' KB' : $sz . ' B') }}</span>
            @endif
        </span>
    </div>
</div>
