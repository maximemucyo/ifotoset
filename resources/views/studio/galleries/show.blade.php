@extends('layouts.app', ['title' => $gallery->title . ' · Studio · ifotoset', 'workspace' => true])

@php
    /*
     * Shared photo-card class names. The Blade cards (partials/photo-card) and the
     * cards built in JS (createPhotoCardElement) both read from this map, so the
     * state classes toggled by the manager (is-cover, is-hidden, is-selected,
     * .hidden on badges/buttons) style identically everywhere.
     */
    $gmCardClasses = [
        // Photos are the content: shown whole in a neutral square, no crop, radius or zoom
        'card' => 'photo-card group relative flex flex-col transition-opacity',
        'media' => 'photo-media relative flex aspect-square min-h-0 cursor-pointer overflow-hidden items-center justify-center bg-muted p-[7%] outline-2 outline-offset-2 outline-transparent transition-[background-color,outline-color] duration-150 hover:bg-border/70 group-[.is-selected]:bg-border/70 group-[.is-selected]:outline-foreground',
        'img' => 'size-full object-contain group-[.is-hidden]:opacity-40 group-[.is-hidden]:grayscale',
        'select' => 'absolute left-2 top-2 z-20 flex size-6 items-center justify-center rounded-md border-[1.5px] border-foreground/35 bg-background/90 text-transparent opacity-0 shadow-xs transition hover:border-foreground focus-visible:opacity-100 group-hover:opacity-100 group-[.is-selected]:border-foreground group-[.is-selected]:bg-foreground group-[.is-selected]:text-background group-[.is-selected]:opacity-100 group-data-[selecting=true]/grid:opacity-100 [@media(hover:none)]:opacity-100',
        'actions' => 'absolute right-2 top-2 z-20 flex items-center gap-1 opacity-0 transition duration-150 focus-within:opacity-100 group-hover:opacity-100 group-data-[selecting=true]/grid:invisible [@media(hover:none)]:opacity-100',
        'actionBtn' => 'size-7 items-center justify-center rounded-md bg-black/55 text-white shadow-sm transition-colors hover:bg-black/75 focus-visible:outline-2 focus-visible:outline-white [&:not(.hidden)]:inline-flex',
        'badgeLight' => 'items-center gap-1 rounded-md bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-black shadow-sm [&:not(.hidden)]:inline-flex',
        'badgeDark' => 'items-center gap-1 rounded-md bg-black/65 px-1.5 py-0.5 text-[10px] font-medium text-white [&:not(.hidden)]:inline-flex',
        'footer' => 'flex items-center gap-1.5 px-0.5 pt-1.5 text-[11px] text-muted-foreground group-data-[density=compact]/grid:hidden',
    ];

    $gmIconHtml = fn (string $name, string $class, $stroke = 2) => trim(\Illuminate\Support\Facades\Blade::render(
        '<x-icon :name="$n" :class="$c" :stroke-width="$s" />', ['n' => $name, 'c' => $class, 's' => $stroke]
    ));
    $gmIcons = [
        'check' => $gmIconHtml('check', 'size-3.5', 3),
        'star' => $gmIconHtml('star', 'size-4'),
        'starSm' => $gmIconHtml('star', 'size-3 fill-current'),
        'eye' => $gmIconHtml('eye', 'size-4'),
        'eye-off' => $gmIconHtml('eye-off', 'size-4'),
        'eyeOffSm' => $gmIconHtml('eye-off', 'size-3'),
        'trash' => $gmIconHtml('trash-2', 'size-4'),
        'playSm' => $gmIconHtml('play', 'size-3 fill-current'),
        'spinner' => $gmIconHtml('loader-circle', 'size-3.5 animate-spin'),
        'alert' => $gmIconHtml('triangle-alert', 'size-4'),
    ];

@endphp

@section('content')
<!-- Gallery Configuration JSON -->
<script id="gallery-config" type="application/json">
{!! json_encode([
    'galleryUuid' => $gallery->uuid,
    'csrfToken' => csrf_token(),
    'photosUrl' => route('studio.galleries.photos', $gallery->uuid),
    'coverUrl' => route('studio.galleries.cover', $gallery->uuid),
    'sets' => $sets,
    'setsUrl' => route('studio.galleries.sets.store', $gallery->uuid),
    'moveToSetUrl' => route('studio.galleries.photos.set', $gallery->uuid),
    'shareLinksUrl' => route('studio.galleries.share-links.store', $gallery->uuid),
    'toggleHideUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'deletePhotoUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'initialTotalCount' => $totalPhotosCount,
    'initialNextCursor' => $initialNextCursor,
    'initialHasMore' => $initialHasMore,
    'uploadRequestUrl' => route('studio.uploads.request'),
    'uploadConfirmUrl' => route('studio.uploads.confirm'),
    'uploadAbortUrl' => route('studio.uploads.abort'),
    'videoStatusUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'storageStatsUrl' => route('studio.storage.stats'),
    // Members can't open Billing, so they get no plan links and are told to ask the owner instead.
    'canManageBilling' => Gate::allows('studio.billing'),
    'billingCheckoutUrl' => Gate::allows('studio.billing') ? route('studio.billing.index') . '?return_to=' . urlencode(request()->getRequestUri()) : '',
    'billingPlansUrl' => Gate::allows('studio.billing') ? route('studio.billing.index') . '?return_to=' . urlencode(request()->getRequestUri()) : '',
    'userStorage' => $userStorage ?? [],
    'userVideo' => [
        'has_video' => request()->account()->hasVideoSupport(),
        'used_seconds' => request()->account()->video_seconds_used,
        'reserved_seconds' => request()->account()->video_seconds_reserved,
        'limit_seconds' => request()->account()->plan?->video_limit_seconds ?? 0,
        'available_seconds' => request()->account()->getAvailableVideoSeconds(),
        'formatted' => request()->account()->getVideoUsageFormatted(),
    ],
    'upgradePlans' => collect(Gate::allows('studio.billing') ? ($upgradePlans ?? []) : [])->map(fn ($p) => [
        'id' => $p->id,
        'slug' => $p->slug,
        'name' => $p->name,
        'price' => number_format($p->monthly_price, 0) . ' RWF',
        'storage' => \App\Support\Bytes::format($p->storage_limit),
        'video_limit' => $p->video_limit_seconds > 0
            ? ($p->video_limit_seconds >= 3600
                ? round($p->video_limit_seconds / 3600, 1) . ' hrs video'
                : round($p->video_limit_seconds / 60) . ' mins video')
            : 'No video',
        'checkoutUrl' => route('studio.billing.checkout', $p->slug) . '?return_to=' . urlencode(request()->getRequestUri()),
    ])->values(),
    'billingReturnSuccess' => $billingReturnSuccess ?? false,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

<!-- Initial Photos JSON Hydration Data -->
<script id="gallery-initial-photos" type="application/json">
{!! $initialPhotosJson !!}
</script>

@include('studio.galleries.partials.manager-script')

<div id="studio-gallery-manager"
     class="relative flex min-h-0 flex-1 flex-col"
     x-data="studioGalleryManager()"
     @keydown.escape.window="if (!lightboxOpen && (selectMode || selectedCount > 0)) clearSelection()"
     x-init="if (new URLSearchParams(location.search).has('upload')) $nextTick(() => $dispatch('open-modal', 'upload-photos-modal'))"
     @dragenter.window="if ($event.dataTransfer && Array.from($event.dataTransfer.types || []).includes('Files')) isDragging = true">

    <x-studio.gallery-workspace :gallery="$gallery" active="photos">
    <x-slot:rail>
        {{-- Sets: pick one to show only its photos; drag to reorder --}}
        <nav aria-label="Sets">
            <div class="flex items-center justify-between px-3 pb-1">
                <h2 class="text-[13px] font-medium text-foreground">Sets</h2>
                <button type="button" class="btn btn-ghost btn-icon btn-xs text-muted-foreground" @click="startAddSet()" title="Add set" aria-label="Add set">
                    <x-icon name="plus" class="size-4" />
                </button>
            </div>
            <ul>
                <li>
                    <button type="button" @click="showSet('')" :aria-current="activeSet === '' && 'true'"
                            class="flex w-full items-baseline justify-between gap-3 rounded-lg px-3 py-2 text-left text-[13px] transition-colors"
                            :class="activeSet === '' ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'">
                        <span>All photos</span>
                        <span class="tabular-nums" x-text="totalPhotos">{{ $totalPhotosCount }}</span>
                    </button>
                </li>
                <template x-for="set in sets" :key="set.uuid">
                    <li draggable="true"
                        @dragstart="draggingSet = set.uuid; $event.dataTransfer.effectAllowed = 'move'"
                        @dragend="draggingSet = null"
                        @dragover.prevent="$event.dataTransfer.dropEffect = 'move'"
                        @drop.prevent="draggingSet && moveSet(draggingSet, set.uuid); draggingSet = null"
                        :class="draggingSet === set.uuid && 'opacity-40'">
                        <button type="button" @click="showSet(set.uuid)" :aria-current="activeSet === set.uuid && 'true'"
                                class="group/set flex w-full cursor-grab items-baseline justify-between gap-3 rounded-lg px-3 py-2 text-left text-[13px] transition-colors active:cursor-grabbing"
                                :class="activeSet === set.uuid ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'">
                            <span class="truncate" x-text="set.name"></span>
                            <span class="tabular-nums" x-text="set.count"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <form x-show="addingSet" x-cloak class="px-1 pt-1" @submit.prevent="addSet()">
                <input id="new-set-name" type="text" class="input h-8 text-[13px]" maxlength="80" placeholder="e.g. Ceremony"
                       x-model="setName" @keydown.escape.stop="addingSet = false" @blur="addSet()" aria-label="New set name">
            </form>
            <p x-show="!sets.length && !addingSet" class="px-3 pt-2 text-[12px] leading-relaxed text-muted-foreground"
               @if(count($sets)) style="display: none;" @endif>
                Split the gallery into parts, like Ceremony and Reception. Clients see them as tabs.
            </p>
        </nav>
    </x-slot:rail>

    {{-- ============ Photos tab ============ --}}
    <section aria-label="Photos and videos">

        <!-- Upload Dropzone Hero (when gallery is empty) -->
        <div x-show="totalPhotos === 0"
             x-cloak
             class="group/drop relative cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-border-strong/70 bg-card/60 px-6 py-16 text-center transition-all hover:border-foreground/30 hover:bg-card sm:py-20"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addDropped($event.dataTransfer)"
             @click="$dispatch('open-modal', 'upload-photos-modal')"
             :class="isDragging ? '!border-brand bg-brand/5 ring-4 ring-brand/10' : ''"
             style="{{ $photos->isEmpty() ? '' : 'display: none;' }}">
            <div class="pointer-events-none absolute inset-0 bg-dots opacity-50 mask-radial"></div>
            <div class="relative mx-auto flex max-w-md flex-col items-center">
                <div class="relative mb-5">
                    <div class="absolute inset-0 -z-10 scale-150 rounded-full bg-brand/10 blur-2xl"></div>
                    <div class="flex size-16 items-center justify-center rounded-2xl border border-border bg-card text-brand shadow-sm transition-transform duration-300 group-hover/drop:-translate-y-1">
                        <x-icon name="cloud-upload" class="size-7" />
                    </div>
                </div>
                <h2 class="text-lg font-semibold tracking-tight text-foreground">Drop photos and videos here</h2>
                <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                    Upload full-resolution images and videos. Thumbnails are generated automatically and your clients see them the moment they are ready.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" class="btn btn-primary">
                        <x-icon name="upload" /> Browse files
                    </button>
                    <span class="text-xs text-muted-foreground">or drag and drop</span>
                </div>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px] text-subtle">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="image" class="size-3.5" /> JPG, PNG, WebP, AVIF up to 50 MB</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="video" class="size-3.5" /> MP4, MOV, WebM</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="shield-check" class="size-3.5" /> Checksummed and resumable</span>
                </div>
            </div>
        </div>

        <!-- Sets on phones, where the rail is hidden -->
        <div x-show="totalPhotos > 0" class="-mx-4 mb-3 overflow-x-auto px-4 [scrollbar-width:none] lg:hidden"
             style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
            <div class="flex w-max gap-1.5">
                <button type="button" class="h-8 rounded-full px-3.5 text-[13px] transition-colors" @click="showSet('')"
                        :class="activeSet === '' ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground'">All</button>
                <template x-for="set in sets" :key="set.uuid">
                    <button type="button" class="h-8 rounded-full px-3.5 text-[13px] transition-colors" @click="showSet(set.uuid)" x-text="set.name"
                            :class="activeSet === set.uuid ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground'"></button>
                </template>
                <form x-show="addingSet" x-cloak @submit.prevent="addSet()">
                    <input type="text" class="input h-8 w-40 rounded-full text-[13px]" maxlength="80" placeholder="Set name"
                           x-model="setName" @keydown.escape.stop="addingSet = false" @blur="addSet()" aria-label="New set name"
                           x-effect="addingSet && $nextTick(() => $el.focus())">
                </form>
                <button type="button" x-show="!addingSet" class="inline-flex h-8 items-center gap-1 rounded-full px-3 text-[13px] text-muted-foreground" @click="addingSet = true; setName = ''">
                    <x-icon name="plus" class="size-3.5" /> Set
                </button>
            </div>
        </div>

        <!-- Grid toolbar -->
        <div x-show="totalPhotos > 0"
             class="mb-4 flex flex-wrap items-center justify-between gap-3"
             style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
            <div class="flex min-w-0 items-center gap-2.5">
                <form x-show="renamingSet" x-cloak @submit.prevent="renameSet()">
                    <input id="rename-set-name" type="text" class="input h-8 w-56 text-[14px] font-semibold" maxlength="80"
                           x-model="setName" @keydown.escape.stop="renamingSet = false" @blur="renameSet()" aria-label="Set name">
                </form>
                <h2 x-show="!renamingSet" class="truncate text-[15px] font-semibold text-foreground" x-text="currentSet ? currentSet.name : 'All photos'">All photos</h2>
                <span class="badge badge-neutral tabular-nums" x-show="!search.trim()" x-text="viewCount">{{ $totalPhotosCount }}</span>
                <div x-show="currentSet && !renamingSet" x-cloak x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape="close()">
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" aria-label="Set actions">
                        <x-icon name="ellipsis" />
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="menu absolute left-0 top-full z-30 mt-2 w-48" role="menu">
                        <button type="button" class="menu-item" role="menuitem" @click="close(); startRenameSet()"><x-icon name="pencil" /> Rename</button>
                        <button type="button" class="menu-item" role="menuitem" x-show="sets[0]?.uuid !== activeSet" @click="close(); moveSet(activeSet, -1)"><x-icon name="arrow-up" /> Move up</button>
                        <button type="button" class="menu-item" role="menuitem" x-show="sets[sets.length - 1]?.uuid !== activeSet" @click="close(); moveSet(activeSet, 1)"><x-icon name="arrow-down" /> Move down</button>
                        <div class="menu-separator"></div>
                        <button type="button" class="menu-item menu-item-danger" role="menuitem" @click="close(); deleteSet()"><x-icon name="trash-2" /> Delete set</button>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="input-group w-40 sm:w-52" role="search">
                    <x-icon name="search" class="input-icon" />
                    <input type="search" class="input h-8 text-[13px]" placeholder="Find by file name" aria-label="Find photos by file name"
                           x-model="search" @input="searchChanged()" @keydown.escape.stop="clearSearch(); $el.blur()" maxlength="100">
                </div>
                <div class="tabs" role="tablist" aria-label="Grid density">
                    <button type="button" class="tab" role="tab" :aria-selected="density === 'comfortable'" @click="density = 'comfortable'" title="Comfortable grid" aria-label="Comfortable grid">
                        <x-icon name="layout-grid" class="size-4" />
                    </button>
                    <button type="button" class="tab" role="tab" :aria-selected="density === 'compact'" @click="density = 'compact'" title="Compact grid" aria-label="Compact grid">
                        <x-icon name="grid" class="size-4" />
                    </button>
                </div>
                <button type="button" class="btn btn-outline btn-sm"
                        :class="(selectMode || selectedCount > 0) && 'bg-muted'"
                        @click="(selectMode || selectedCount > 0) ? clearSelection() : (selectMode = true)">
                    <x-icon name="check-check" />
                    <span x-text="(selectMode || selectedCount > 0) ? 'Done' : 'Select'">Select</span>
                </button>
                <button type="button" class="btn btn-outline btn-sm" @click="$dispatch('open-modal', 'upload-photos-modal')">
                    <x-icon name="plus" /> <span class="hidden sm:inline">Add photos</span>
                </button>
            </div>
        </div>

        <!-- Photos Grid (Stable DOM container) -->
        <div x-show="totalPhotos > 0"
             class="group/grid grid select-none gap-3 sm:gap-4"
             :class="density === 'compact' ? 'grid-cols-3 !gap-2 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'"
             :data-density="density"
             :data-selecting="(selectMode || selectedCount > 0) ? 'true' : 'false'"
             id="studio-photos-grid"
             style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
            @foreach($photos as $photo)
                @include('studio.galleries.partials.photo-card', ['photo' => $photo])
            @endforeach
        </div>

        <!-- A search with no matches -->
        <div x-show="search.trim() && !loadingPhotos && !hasMore && photos.length === 0" x-cloak
             class="rounded-xl border border-dashed border-border-strong/70 px-6 py-14 text-center">
            <p class="text-sm font-medium text-foreground">No file names contain “<span x-text="search.trim()"></span>”</p>
            <p class="mt-1 text-[13px] text-muted-foreground" x-show="currentSet">This only looks inside <span x-text="currentSet?.name"></span>.</p>
            <button type="button" class="btn btn-outline btn-sm mt-4" @click="clearSearch()">Clear search</button>
        </div>

        <!-- An open set with nothing in it yet -->
        <div x-show="currentSet && !search.trim() && !loadingPhotos && !hasMore && photos.length === 0" x-cloak
             class="rounded-xl border border-dashed border-border-strong/70 px-6 py-14 text-center">
            <p class="text-sm font-medium text-foreground">No photos in this set yet</p>
            <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">Add photos here, or select photos in All photos and choose Move to set.</p>
            <button type="button" class="btn btn-outline btn-sm mt-4" @click="$dispatch('open-modal', 'upload-photos-modal')">
                <x-icon name="plus" /> Add photos
            </button>
        </div>

        <!-- Infinite Scroll Sentinel -->
        <div id="infinite-scroll-sentinel"
             class="py-8 text-center"
             x-show="hasMore">
            <div x-show="loadingPhotos" class="inline-flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <x-icon name="loader-circle" class="size-4 animate-spin" />
                <span>Loading more…</span>
            </div>
            <div x-show="!loadingPhotos && hasMore" class="text-xs text-subtle">
                Scroll to load more
            </div>
        </div>
    </section>

    {{-- ============ Full-page drop overlay ============ --}}
    <div x-show="isDragging"
         x-cloak
         x-transition.opacity.duration.150ms
         class="fixed inset-0 z-[70] flex items-center justify-center bg-background/70 p-6 backdrop-blur-sm"
         @dragover.prevent="isDragging = true"
         @dragleave.self="isDragging = false"
         @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addDropped($event.dataTransfer)">
        <div class="pointer-events-none flex w-full max-w-lg flex-col items-center rounded-3xl border-2 border-dashed border-brand bg-card/90 px-8 py-14 text-center shadow-xl">
            <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                <x-icon name="cloud-upload" class="size-7" />
            </div>
            <p class="text-base font-semibold text-foreground">Drop to upload to “{{ $gallery->title }}”</p>
            <p class="mt-1 text-[13px] text-muted-foreground">Photos and videos are uploaded directly and added to the grid as they finish.</p>
        </div>
    </div>

    @include('studio.galleries.partials.bulk-bar')
    @include('studio.galleries.partials.lightbox')
    @include('studio.galleries.partials.upload-dock')
    @include('studio.galleries.partials.upload-modal')

    {{-- Delete confirmation --}}
    <x-ui.modal name="confirm-delete-photos" maxWidth="md">
        <div class="flex items-start gap-4">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                <x-icon name="trash-2" class="size-5" />
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-foreground"
                    x-text="pendingDelete.length > 1 ? 'Move ' + pendingDelete.length + ' items to trash?' : 'Move this item to trash?'">Move to trash?</h3>
                <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                    Removed items disappear from the client gallery right away. You can restore them from
                    <a href="{{ route('studio.trash.index') }}" class="link">Trash</a> until it is permanently purged.
                </p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-outline" @click="pendingDelete = []; $dispatch('close-modal', 'confirm-delete-photos')">Cancel</button>
            <button type="button" class="btn btn-danger" @click="confirmPendingDelete()">
                <x-icon name="trash-2" /> Move to trash
            </button>
        </div>
    </x-ui.modal>
    </x-studio.gallery-workspace>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.js"></script>
@endpush
