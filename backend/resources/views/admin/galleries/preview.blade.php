@extends('layouts.admin', ['title' => 'Moderation Preview - ' . $gallery->title])

@section('content')
<div x-data="{
    takedownModalOpen: false,
    takedownReason: '',
    previewOpen: false,
    currentMedia: null,
    activeArtplayer: null,
    openPreview(media) {
        this.currentMedia = media;
        this.previewOpen = true;
        document.body.style.overflow = 'hidden';
        if (media.isVideo) {
            this.$nextTick(() => {
                this.initArtplayer(media);
            });
        }
    },
    closePreview() {
        this.teardownArtplayer();
        this.previewOpen = false;
        this.currentMedia = null;
        document.body.style.overflow = '';
    },
    initArtplayer(media) {
        this.teardownArtplayer();
        const container = document.getElementById('admin-artplayer-container');
        if (!container) return;
        const videoUrl = media.videoUrl || '';
        const posterUrl = media.posterUrl || media.imageUrl || '';

        if (window.Artplayer) {
            try {
                this.activeArtplayer = new window.Artplayer({
                    container: container,
                    url: videoUrl,
                    poster: posterUrl,
                    volume: 0.7,
                    isLive: false,
                    muted: true,
                    autoplay: true,
                    pip: true,
                    autoSize: false,
                    autoMini: false,
                    setting: true,
                    loop: false,
                    playbackRate: true,
                    aspectRatio: true,
                    fullscreen: true,
                    fullscreenWeb: true,
                    playsInline: true,
                    airplay: true,
                    theme: '#f0532a',
                    customType: {
                        m3u8: function (video, url, artInstance) {
                            if (window.Hls && window.Hls.isSupported()) {
                                if (artInstance.hls) artInstance.hls.destroy();
                                const hls = new window.Hls();
                                hls.loadSource(url);
                                hls.attachMedia(video);
                                artInstance.hls = hls;
                                artInstance.on('destroy', () => hls.destroy());
                            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                video.src = url;
                            } else {
                                artInstance.notice.show = 'Unsupported video format';
                            }
                        },
                    },
                });
            } catch (e) {
                console.error('[Admin Preview] Artplayer init failed:', e);
            }
        }

        if (!this.activeArtplayer) {
            const video = document.createElement('video');
            video.src = videoUrl;
            video.poster = posterUrl;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            video.className = 'max-h-[75vh] max-w-full object-contain rounded-xl shadow-2xl';
            container.appendChild(video);
        }
    },
    teardownArtplayer() {
        if (this.activeArtplayer) {
            try { this.activeArtplayer.destroy(true); } catch (e) {}
            this.activeArtplayer = null;
        }
        const container = document.getElementById('admin-artplayer-container');
        if (container) {
            container.innerHTML = '';
        }
    }
}">
    @php
        $fmtBytes = function ($b) {
            $b = (float) $b; $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
            while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
            return ($i === 0 ? (int) $b : number_format($b, 1)) . ' ' . $u[$i];
        };
        $isTakenDown = $gallery->isTakenDown();
    @endphp

    <x-ui.page-header :title="$gallery->title" :back="route('admin.galleries.index')" back-label="All galleries" eyebrow="Moderation preview">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                @if($isTakenDown)
                    <span class="badge badge-danger"><x-icon name="ban" /> Taken down</span>
                @else
                    <span class="badge badge-success badge-dot">Active</span>
                @endif
                @if($gallery->visibility === 'public')
                    <span class="badge badge-neutral"><x-icon name="globe" /> Public</span>
                @elseif($gallery->visibility === 'password')
                    <span class="badge badge-warning"><x-icon name="lock" /> PIN protected</span>
                @else
                    <span class="badge badge-neutral"><x-icon name="eye-off" /> Private</span>
                @endif
                <span class="font-mono text-xs text-muted-foreground">/g/{{ $gallery->user?->username }}/{{ $gallery->slug }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if($photographer->username)
                <a href="{{ $gallery->public_url }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                    <x-icon name="external-link" /> Public view
                </a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <!-- Admin moderation mode notice -->
    <div class="alert alert-info">
        <x-icon name="shield-check" />
        <div>
            <p class="font-medium">You're viewing this gallery as an administrator.</p>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Privacy PINs, client invitations and expiry dates are bypassed. Nothing here is visible to the photographer or their clients.</p>
        </div>
    </div>

    @if($isTakenDown)
        <div class="alert alert-danger mt-4">
            <x-icon name="ban" />
            <div class="min-w-0">
                <p class="font-medium">This gallery is currently taken down</p>
                <p class="mt-1 text-[13px] text-foreground"><span class="text-muted-foreground">Reason:</span> {{ $gallery->takedown_reason ?? 'Content moderation flag.' }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Taken down {{ $gallery->taken_down_at?->format('M j, Y H:i') }}
                    @if($gallery->takenDownBy)
                        by {{ $gallery->takenDownBy->name }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    <!-- Details -->
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:col-span-2">
            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="images" class="size-3.5" /> Photos</div>
                <div class="stat-value mt-2 !text-xl">{{ number_format($gallery->photo_count) }}</div>
            </div>
            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="hard-drive" class="size-3.5" /> Storage</div>
                <div class="stat-value mt-2 !text-xl">{{ $fmtBytes($gallery->stats?->total_bytes ?? 0) }}</div>
            </div>
            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="download" class="size-3.5" /> Downloads</div>
                <div class="stat-value mt-2 !text-xl">{{ number_format($gallery->stats?->downloads_count ?? 0) }}</div>
            </div>
            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="heart" class="size-3.5" /> Favorites</div>
                <div class="stat-value mt-2 !text-xl">{{ number_format($gallery->stats?->favorites_count ?? 0) }}</div>
            </div>
        </div>
        <div class="card flex items-center gap-3 p-4">
            <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url ?? null" size="md" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-[13px] font-medium text-foreground">{{ $photographer->name }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ $photographer->email }}</p>
                <p class="mt-1 text-[11px] text-subtle">
                    @if($gallery->event_date)Event {{ $gallery->event_date->format('M j, Y') }} · @endif
                    Created {{ $gallery->created_at->format('M j, Y') }}
                </p>
            </div>
            <a href="{{ route('admin.users.index', ['search' => $photographer->email]) }}" class="btn btn-ghost btn-icon btn-sm shrink-0" aria-label="Find photographer in users" title="Open in users">
                <x-icon name="arrow-up-right" />
            </a>
        </div>
    </div>

    <!-- Photos grid -->
    <section class="mt-8">
        <div class="mb-4 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold text-foreground">Media</h2>
                <p class="text-xs text-muted-foreground">Click any item to inspect it at full size.</p>
            </div>
            <span class="text-xs tabular-nums text-muted-foreground">{{ $photos->firstItem() ?? 0 }}–{{ $photos->lastItem() ?? 0 }} of {{ $photos->total() }}</span>
        </div>

        @if($photos->isEmpty())
            <x-ui.empty-state title="No media in this gallery" description="The photographer hasn't uploaded any photos or videos yet." icon="images" />
        @else
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 xl:grid-cols-6">
                @foreach($photos as $photo)
                    @php
                        $isVideo = $photo->isVideo();
                        $videoUrl = $isVideo ? ($photo->getStreamUrl() ?? $photo->getUrl()) : null;
                        $posterUrl = $isVideo ? ($photo->getPosterUrl('lg') ?? $photo->getUrl('lg')) : null;
                        $previewUrl = $isVideo ? ($posterUrl ?? $photo->getUrl('md')) : $photo->getUrl('xl');
                    @endphp
                    <button type="button"
                         class="group relative aspect-square cursor-zoom-in select-none overflow-hidden rounded-lg bg-muted ring-1 ring-border transition hover:ring-border-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                         aria-label="Inspect {{ $photo->original_filename }}"
                         @click="openPreview({
                             uuid: '{{ $photo->uuid }}',
                             isVideo: {{ $isVideo ? 'true' : 'false' }},
                             videoUrl: '{{ $videoUrl }}',
                             posterUrl: '{{ $posterUrl }}',
                             imageUrl: '{{ $previewUrl }}',
                             filename: '{{ addslashes($photo->original_filename) }}',
                             duration: '{{ $photo->duration_formatted }}',
                             sizeFormatted: '{{ round($photo->size / 1024) }} KB'
                         })">
                        <img src="{{ $photo->getUrl('md') }}"
                             alt="{{ $photo->original_filename }}"
                             loading="lazy" decoding="async"
                             class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">

                        @if($isVideo)
                            <span class="absolute left-2 top-2 z-10 inline-flex items-center gap-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm">
                                <x-icon name="play" class="size-3 fill-current" />
                                {{ $photo->duration_formatted ?: 'Video' }}
                            </span>
                        @endif

                        <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent p-2 pt-6 text-left text-[10px] text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $photo->original_filename }}</span>
                                <span class="font-mono text-white/70">{{ round($photo->size / 1024) }} KB</span>
                            </span>
                            <x-icon :name="$isVideo ? 'play' : 'zoom-in'" class="size-3.5 shrink-0" />
                        </span>
                    </button>
                @endforeach
            </div>

            @if($photos->hasPages())
                <div class="mt-6">
                    {{ $photos->links() }}
                </div>
            @endif
        @endif
    </section>

    <!-- Moderation action bar -->
    <div class="pointer-events-none sticky bottom-4 z-30 mt-8 flex justify-center">
        <div class="glass pointer-events-auto flex max-w-full flex-wrap items-center justify-center gap-2 rounded-2xl border border-border p-2 shadow-lg sm:flex-nowrap sm:rounded-full sm:pl-4">
            <span class="hidden items-center gap-2 pr-1 text-[13px] text-muted-foreground sm:flex">
                <x-icon name="shield" class="size-4 {{ $isTakenDown ? 'text-destructive' : 'text-success' }}" />
                <span class="whitespace-nowrap">{{ $isTakenDown ? 'Taken down' : 'Live' }} · {{ $gallery->visibility === 'password' ? 'PIN' : ucfirst($gallery->visibility) }}</span>
            </span>
            <span class="hidden h-5 w-px bg-border sm:block"></span>

            <!-- Toggle visibility -->
            <form method="POST" action="{{ route('admin.galleries.visibility', $gallery->uuid) }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm rounded-full">
                    <x-icon :name="$gallery->visibility === 'public' ? 'eye-off' : 'eye'" />
                    {{ $gallery->visibility === 'public' ? 'Make Private' : 'Make Public' }}
                </button>
            </form>

            <!-- Takedown or restore -->
            @if($isTakenDown)
                <form method="POST" action="{{ route('admin.galleries.restore', $gallery->uuid) }}" onsubmit="return confirm('Restore this gallery to normal moderation status? Photographer visibility settings will remain unchanged.');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm rounded-full">
                        <x-icon name="rotate-ccw" /> Restore gallery
                    </button>
                </form>
            @else
                <button type="button" @click="takedownModalOpen = true" class="btn btn-danger btn-sm rounded-full">
                    <x-icon name="ban" /> Take down
                </button>
            @endif
        </div>
    </div>

    <!-- Takedown modal -->
    <div x-show="takedownModalOpen"
         class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
         role="dialog" aria-modal="true"
         style="display: none;">
        <div x-show="takedownModalOpen" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
        <div x-show="takedownModalOpen" x-transition @click.away="takedownModalOpen = false" class="relative w-full max-w-md rounded-2xl border border-border bg-card shadow-2xl">
            <form method="POST" action="{{ route('admin.galleries.takedown', $gallery->uuid) }}">
                @csrf
                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"><x-icon name="ban" class="size-4" /></span>
                            <div>
                                <h3 class="text-[15px] font-semibold text-foreground">Take down gallery</h3>
                                <p class="mt-1 text-[13px] text-muted-foreground">
                                    <span class="font-medium text-foreground">{{ $gallery->title }}</span> will become unavailable to the public with a moderation notice. The photographer's privacy configuration is preserved for a future restore.
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="takedownModalOpen = false" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1 shrink-0" aria-label="Close"><x-icon name="x" /></button>
                    </div>
                    <div>
                        <label class="label" for="takedown-reason">Moderation reason <span class="text-destructive">*</span></label>
                        <textarea id="takedown-reason"
                                  name="reason"
                                  x-model="takedownReason"
                                  rows="3"
                                  required
                                  placeholder="e.g. Copyright infringement notice, Terms of Service violation, illegal content…"
                                  class="textarea"></textarea>
                        <p class="help">Recorded in the admin audit log.</p>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" @click="takedownModalOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="submit" :disabled="!takedownReason.trim()" class="btn btn-danger btn-sm">Confirm takedown</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin media preview (Artplayer for videos, zoom/inspect for photos) -->
    <div x-show="previewOpen"
         x-transition.opacity
         @keydown.escape.window="closePreview()"
         class="fixed inset-0 z-50 flex select-none items-center justify-center bg-black/90 p-4 backdrop-blur-md"
         role="dialog" aria-modal="true"
         style="display: none;">
        <div @click.away="closePreview()" class="relative flex max-h-[92vh] w-full max-w-5xl flex-col items-center">
            <div class="mb-3 flex w-full items-center justify-between gap-3 border-b border-white/10 px-1 pb-3 text-white">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="truncate text-[13px] font-medium" x-text="currentMedia?.filename"></span>
                    <template x-if="currentMedia?.isVideo">
                        <span class="rounded-md bg-white/15 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-white">Video</span>
                    </template>
                    <template x-if="currentMedia?.duration">
                        <span class="font-mono text-xs text-white/60" x-text="currentMedia.duration"></span>
                    </template>
                </div>
                <button type="button" @click="closePreview()" class="flex size-8 items-center justify-center rounded-lg text-white/70 transition-colors hover:bg-white/10 hover:text-white" title="Close preview (Esc)" aria-label="Close preview">
                    <x-icon name="x" class="size-5" />
                </button>
            </div>

            <div class="flex min-h-0 w-full flex-1 items-center justify-center overflow-hidden">
                <template x-if="currentMedia && currentMedia.isVideo">
                    <div class="flex w-full items-center justify-center">
                        <div id="admin-artplayer-container"
                             class="pointer-events-auto flex h-[65vh] max-h-[75vh] w-[85vw] max-w-4xl select-auto items-center justify-center overflow-hidden rounded-xl bg-black shadow-2xl">
                        </div>
                    </div>
                </template>
                <template x-if="currentMedia && !currentMedia.isVideo">
                    <img :src="currentMedia?.imageUrl"
                         :alt="currentMedia?.filename"
                         class="max-h-[75vh] max-w-full rounded-lg object-contain shadow-2xl">
                </template>
            </div>

            <div class="mt-3 flex w-full items-center justify-between border-t border-white/10 px-1 pt-3 text-xs text-white/60">
                <span class="font-mono" x-text="currentMedia?.sizeFormatted"></span>
                <template x-if="currentMedia?.videoUrl || currentMedia?.imageUrl">
                    <a :href="currentMedia.videoUrl || currentMedia.imageUrl" target="_blank" download class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 font-medium text-white/80 transition hover:bg-white/10 hover:text-white">
                        <x-icon name="download" class="size-3.5" /> Download original
                    </a>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.js"></script>
@endpush
