{{-- Studio lightbox (preview + quick actions). Pure-photo surface: black/white overlays are intentional. --}}
<div x-show="lightboxOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="closeLightbox()"
     @keydown.left.window="prevLightboxPhoto()"
     @keydown.right.window="nextLightboxPhoto()"
     class="fixed inset-0 z-[60] flex select-none flex-col bg-black/95 backdrop-blur-md"
     role="dialog" aria-modal="true" aria-label="Media preview"
     style="display: none;">

    <!-- Top Bar -->
    <div class="z-10 flex items-center justify-between gap-3 border-b border-white/10 bg-black/40 px-3 py-2.5 text-white sm:px-5">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="shrink-0 rounded-md bg-white/10 px-2 py-0.5 font-mono text-[11px] tabular-nums text-white/80" x-text="(lightboxIndex + 1) + ' / ' + photos.length"></span>
            <span class="min-w-0 truncate text-sm font-medium" x-text="currentPhoto ? (currentPhoto.original_filename || currentPhoto.filename) : ''"></span>
            <template x-if="currentPhoto && currentPhoto.is_cover">
                <span class="hidden shrink-0 items-center gap-1 rounded-md bg-white px-1.5 py-0.5 text-[10px] font-semibold text-black sm:inline-flex"><x-icon name="star" class="size-3 fill-current" /> Cover</span>
            </template>
            <template x-if="currentPhoto && currentPhoto.is_hidden">
                <span class="hidden shrink-0 items-center gap-1 rounded-md bg-warning px-1.5 py-0.5 text-[10px] font-semibold text-white sm:inline-flex"><x-icon name="eye-off" class="size-3" /> Hidden</span>
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
            <!-- Toggle Hide in Lightbox -->
            <button type="button"
                    @click="if (currentPhoto) toggleHide(currentPhoto.uuid)"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium transition-colors"
                    :class="currentPhoto && currentPhoto.is_hidden ? 'bg-warning text-white hover:bg-warning/90' : 'bg-white/10 text-white hover:bg-white/20'"
                    :title="currentPhoto && currentPhoto.is_hidden ? 'Make visible' : 'Hide photo'">
                <span x-show="!(currentPhoto && currentPhoto.is_hidden)"><x-icon name="eye-off" class="size-4" /></span>
                <span x-show="currentPhoto && currentPhoto.is_hidden"><x-icon name="eye" class="size-4" /></span>
                <span class="hidden sm:inline" x-text="currentPhoto && currentPhoto.is_hidden ? 'Make visible' : 'Hide'"></span>
            </button>

            <!-- Set Cover in Lightbox -->
            <template x-if="currentPhoto && !currentPhoto.is_cover && !currentPhoto.is_hidden">
                <button type="button"
                        @click="setCover(currentPhoto.uuid)"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-white/10 px-2.5 text-xs font-medium text-white transition-colors hover:bg-white/20"
                        title="Set as cover">
                    <x-icon name="star" class="size-4" /> <span class="hidden sm:inline">Set as cover</span>
                </button>
            </template>

            <!-- Download -->
            <template x-if="currentPhoto && (currentPhoto.original_url || currentPhoto.full_url)">
                <a :href="currentPhoto.original_url || currentPhoto.full_url"
                   target="_blank" download
                   class="inline-flex size-8 items-center justify-center rounded-lg bg-white/10 text-white transition-colors hover:bg-white/20"
                   title="Download original" aria-label="Download original">
                    <x-icon name="download" class="size-4" />
                </a>
            </template>

            <!-- Delete in Lightbox -->
            <button type="button"
                    @click="if (currentPhoto) deleteInLightbox(currentPhoto.uuid)"
                    class="inline-flex size-8 items-center justify-center rounded-lg bg-white/10 text-white transition-colors hover:bg-destructive"
                    title="Delete" aria-label="Delete">
                <x-icon name="trash-2" class="size-4" />
            </button>

            <div class="mx-1 h-5 w-px bg-white/15"></div>

            <button type="button"
                    @click="closeLightbox()"
                    class="inline-flex size-8 items-center justify-center rounded-lg text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                    title="Close preview (Esc)" aria-label="Close preview">
                <x-icon name="x" class="size-5" />
            </button>
        </div>
    </div>

    <!-- Main Media Container with Navigation Arrows -->
    <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden p-3 sm:p-6"
         @click.self="closeLightbox()">

        <!-- Prev Arrow -->
        <button type="button"
                @click.stop="prevLightboxPhoto()"
                x-show="photos.length > 1"
                class="absolute left-2 z-20 inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:scale-105 hover:bg-white/20 sm:left-5 sm:size-11"
                title="Previous (Left arrow)" aria-label="Previous">
            <x-icon name="chevron-left" class="size-5" />
        </button>

        <!-- Media View (Image or Video) -->
        <template x-if="currentPhoto">
            <div class="relative flex max-h-full max-w-full items-center justify-center">
                <template x-if="currentPhoto.is_video || currentPhoto.media_type === 'video'">
                    <div class="relative flex flex-col items-center">
                        <template x-if="currentPhoto.status === 'processing'">
                            <div class="flex max-w-sm flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
                                <x-icon name="loader-circle" class="size-8 animate-spin text-white/80" />
                                <h4 class="text-sm font-semibold text-white">Video is processing</h4>
                                <p class="text-xs leading-relaxed text-white/60">Optimizing the video for fast streaming and generating thumbnails. It appears here automatically once ready.</p>
                            </div>
                        </template>
                        <template x-if="currentPhoto.status !== 'processing'">
                            <div class="relative flex max-h-[82vh] max-w-[90vw] items-center justify-center">
                                <div id="studio-artplayer-container"
                                     x-init="$nextTick(() => initStudioArtplayer())"
                                     class="pointer-events-auto flex h-[70vh] max-h-[82vh] w-[85vw] max-w-4xl select-auto items-center justify-center overflow-hidden rounded-lg bg-black shadow-2xl">
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!currentPhoto.is_video && currentPhoto.media_type !== 'video'">
                    <img :src="currentPhoto.full_url || currentPhoto.large_url || currentPhoto.original_url || currentPhoto.medium_url || currentPhoto.thumbnail_url"
                         :alt="currentPhoto.original_filename"
                         decoding="async"
                         class="max-h-[80vh] max-w-[92vw] select-none rounded-md object-contain shadow-2xl transition-all duration-200 sm:max-w-[84vw]" />
                </template>
            </div>
        </template>

        <!-- Next Arrow -->
        <button type="button"
                @click.stop="nextLightboxPhoto()"
                x-show="photos.length > 1"
                class="absolute right-2 z-20 inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:scale-105 hover:bg-white/20 sm:right-5 sm:size-11"
                title="Next (Right arrow)" aria-label="Next">
            <x-icon name="chevron-right" class="size-5" />
        </button>
    </div>

    <!-- Bottom Bar / Photo Metadata -->
    <div class="flex items-center justify-between gap-3 border-t border-white/10 bg-black/40 px-4 py-2.5 text-xs text-white/60 sm:px-5">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 tabular-nums">
            <template x-if="currentPhoto && currentPhoto.duration">
                <span class="inline-flex items-center gap-1.5 text-white/80"><x-icon name="play" class="size-3 fill-current" /> <span x-text="currentPhoto.duration"></span></span>
            </template>
            <template x-if="currentPhoto && currentPhoto.width && currentPhoto.height">
                <span x-text="currentPhoto.width + ' × ' + currentPhoto.height + ' px'"></span>
            </template>
            <template x-if="currentPhoto && currentPhoto.size">
                <span x-text="formatBytes(currentPhoto.size)"></span>
            </template>
        </div>
        <div class="hidden items-center gap-1.5 text-white/40 sm:flex">
            <span class="inline-flex rounded border border-white/15 px-1 py-0.5"><x-icon name="arrow-left" class="size-3" /></span>
            <span class="inline-flex rounded border border-white/15 px-1 py-0.5"><x-icon name="arrow-right" class="size-3" /></span>
            <span>to navigate</span>
            <span class="ml-2 rounded border border-white/15 px-1.5 py-0.5 font-sans text-[10px]">Esc</span>
            <span>to close</span>
        </div>
    </div>
</div>
