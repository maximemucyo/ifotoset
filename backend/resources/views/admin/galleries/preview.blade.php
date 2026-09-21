@extends('layouts.admin', ['title' => 'Moderation Preview - ' . $gallery->title])

@section('content')
<div class="space-y-6" x-data="{ takedownModalOpen: false, takedownReason: '' }">
    <!-- Admin Moderation Mode Banner -->
    <div class="rounded-xl border border-primary/30 bg-primary/10 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary text-primary-foreground flex items-center justify-center font-bold shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <span>🛡 Admin Moderation Preview</span>
                    @if($gallery->isTakenDown())
                        <span class="px-2 py-0.5 rounded bg-destructive text-destructive-foreground text-[10px] font-extrabold uppercase tracking-wider">
                            Taken Down
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded bg-green-500/20 text-green-700 dark:text-green-400 text-[10px] font-extrabold uppercase tracking-wider">
                            Active
                        </span>
                    @endif
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Viewing full gallery content with administrator privileges. Privacy PINs, client invitations, and expiration dates are bypassed.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.galleries.index') }}" class="px-3 py-1.5 rounded-lg border border-border bg-card hover:bg-secondary text-xs font-semibold text-foreground transition-colors">
                &larr; Back to Galleries
            </a>
        </div>
    </div>

    <!-- Gallery Details & Controls Card -->
    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-border">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ $gallery->title }}</h1>
                    <x-ui.badge :variant="$gallery->visibility === 'public' ? 'default' : ($gallery->visibility === 'password' ? 'warning' : 'muted')">
                        {{ ucfirst($gallery->visibility) }}
                    </x-ui.badge>
                </div>
                <div class="text-xs text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>Photographer: <strong>{{ $photographer->name }}</strong> ({{ $photographer->email }})</span>
                    <span>&bull;</span>
                    <span>Slug: <code class="font-mono text-foreground">{{ $gallery->slug }}</code></span>
                    @if($gallery->event_date)
                        <span>&bull;</span>
                        <span>Event Date: {{ $gallery->event_date->format('M j, Y') }}</span>
                    @endif
                    <span>&bull;</span>
                    <span>Created: {{ $gallery->created_at->format('M j, Y') }}</span>
                </div>
            </div>

            <!-- Moderation Toolbar -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <!-- Toggle Visibility -->
                <form method="POST" action="{{ route('admin.galleries.visibility', $gallery->uuid) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-3.5 py-2 rounded-xl border border-border bg-secondary/50 hover:bg-secondary text-xs font-semibold text-foreground transition-colors cursor-pointer">
                        {{ $gallery->visibility === 'public' ? 'Make Private' : 'Make Public' }}
                    </button>
                </form>

                <!-- Takedown or Restore -->
                @if($gallery->isTakenDown())
                    <form method="POST" action="{{ route('admin.galleries.restore', $gallery->uuid) }}" onsubmit="return confirm('Restore this gallery to normal moderation status? Photographer visibility settings will remain unchanged.');">
                        @csrf
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold shadow-sm transition-colors cursor-pointer flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Restore Gallery
                        </button>
                    </form>
                @else
                    <button type="button" @click="takedownModalOpen = true" class="px-3.5 py-2 rounded-xl bg-destructive hover:bg-destructive/90 text-destructive-foreground text-xs font-bold shadow-sm transition-colors cursor-pointer flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Take Down Gallery
                    </button>
                @endif

                @if($photographer->username)
                    <a href="{{ $gallery->public_url }}" target="_blank" class="px-3.5 py-2 rounded-xl bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors flex items-center gap-1">
                        <span>Public View</span>
                        <span>&nearr;</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- Takedown Information Alert if taken down -->
        @if($gallery->isTakenDown())
            <div class="p-4 rounded-xl bg-destructive/10 border border-destructive/20 text-xs space-y-1">
                <div class="font-bold text-destructive flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Gallery Currently Taken Down
                </div>
                <div class="text-foreground">
                    <strong>Reason:</strong> {{ $gallery->takedown_reason ?? 'Content moderation flag.' }}
                </div>
                <div class="text-muted-foreground text-[11px]">
                    Taken down on {{ $gallery->taken_down_at?->format('M j, Y H:i') }}
                    @if($gallery->takenDownBy)
                        by {{ $gallery->takenDownBy->name }}
                    @endif
                </div>
            </div>
        @endif

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                <div class="text-xs text-muted-foreground font-medium">Photos</div>
                <div class="text-xl font-bold text-foreground mt-1">{{ $gallery->photo_count }}</div>
            </div>
            <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                <div class="text-xs text-muted-foreground font-medium">Storage Used</div>
                <div class="text-xl font-bold text-foreground mt-1">{{ round(($gallery->stats?->total_bytes ?? 0) / (1024 * 1024), 1) }} MB</div>
            </div>
            <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                <div class="text-xs text-muted-foreground font-medium">Downloads</div>
                <div class="text-xl font-bold text-foreground mt-1">{{ $gallery->stats?->downloads_count ?? 0 }}</div>
            </div>
            <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                <div class="text-xs text-muted-foreground font-medium">Favorites</div>
                <div class="text-xl font-bold text-foreground mt-1">{{ $gallery->stats?->favorites_count ?? 0 }}</div>
            </div>
        </div>
    </div>

    <!-- Photos Grid -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-foreground">Gallery Photographs ({{ $photos->total() }})</h3>
            <span class="text-xs text-muted-foreground">Showing {{ $photos->firstItem() ?? 0 }}-{{ $photos->lastItem() ?? 0 }} of {{ $photos->total() }}</span>
        </div>

        @if($photos->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-12 text-center text-muted-foreground text-sm">
                No photographs uploaded to this gallery yet.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($photos as $photo)
                    <div class="group relative rounded-xl overflow-hidden bg-muted border border-border aspect-square shadow-sm hover:shadow-md transition-all">
                        <img src="{{ $photo->getUrl('md') }}"
                             alt="{{ $photo->original_filename }}"
                             loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity p-2 flex flex-col justify-between text-white text-[10px]">
                            <div class="truncate font-medium">{{ $photo->original_filename }}</div>
                            <div class="flex items-center justify-between font-mono">
                                <span>{{ round($photo->size / 1024) }} KB</span>
                                <a href="{{ $photo->getUrl('xl') }}" target="_blank" class="px-2 py-1 rounded bg-white/20 hover:bg-white/40 text-white font-bold transition-colors">
                                    Full &nearr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-4">
                {{ $photos->links() }}
            </div>
        @endif
    </div>

    <!-- Takedown Modal Dialog -->
    <div x-show="takedownModalOpen"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-foreground/50 backdrop-blur-sm"
         style="display: none;">
        <div @click.away="takedownModalOpen = false" class="bg-card border border-border rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2 text-destructive font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Take Down Gallery
                </div>
                <button @click="takedownModalOpen = false" class="text-muted-foreground hover:text-foreground text-sm">&times;</button>
            </div>

            <p class="text-xs text-muted-foreground">
                Taking down <strong>{{ $gallery->title }}</strong> will make it unavailable to the public with a moderation notice. The photographer's original privacy configuration will be preserved for future restoration.
            </p>

            <form method="POST" action="{{ route('admin.galleries.takedown', $gallery->uuid) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1.5">Specific Moderation Reason <span class="text-destructive">*</span></label>
                    <textarea name="reason"
                              x-model="takedownReason"
                              rows="3"
                              required
                              placeholder="e.g. Copyright infringement notice from copyright holder, Terms of Service violation, illegal content..."
                              class="w-full rounded-xl border border-border bg-input px-3.5 py-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="takedownModalOpen = false" class="px-4 py-2 rounded-xl border border-border hover:bg-secondary text-xs font-semibold text-foreground">
                        Cancel
                    </button>
                    <button type="submit" :disabled="!takedownReason.trim()" class="px-4 py-2 rounded-xl bg-destructive hover:bg-destructive/90 text-destructive-foreground text-xs font-bold disabled:opacity-50 transition-all cursor-pointer">
                        Confirm Takedown
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
