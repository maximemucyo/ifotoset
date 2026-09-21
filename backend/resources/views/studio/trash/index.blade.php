@extends('layouts.app', ['title' => 'Trash - Studio'])

@section('content')
<div class="space-y-6" x-data="{ emptyModalOpen: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Trash &amp; Recovery</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground border border-border">
                    {{ $retentionDays }}-Day Retention
                </span>
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                Soft-deleted items are safely preserved for {{ $retentionDays }} days before being permanently removed from Backblaze B2 storage.
            </p>
        </div>

        @if($totalItems > 0)
            <button type="button"
                    @click="emptyModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-destructive/10 text-destructive border border-destructive/20 hover:bg-destructive hover:text-white transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Empty All Trash ({{ $totalItems }})
            </button>
        @endif
    </div>

    <!-- Storage Notification & Retention Info -->
    <div class="rounded-xl border border-border bg-card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16M9 11h6" />
                </svg>
            </div>
            <div>
                <h4 class="text-xs font-bold text-foreground">Trash Policy &amp; Cloud Storage</h4>
                <p class="text-xs text-muted-foreground mt-0.5">
                    @if($trashBytes > 0)
                        Items in trash currently occupy <span class="font-semibold text-foreground">{{ number_format($trashBytes / (1024 * 1024), 1) }} MB</span>.
                        Permanently deleting them will immediately release this space on B2 cloud storage.
                    @else
                        Items in trash are automatically purged after {{ $retentionDays }} days. You can restore them anytime before then.
                    @endif
                </p>
            </div>
        </div>
        <div class="text-xs font-medium text-muted-foreground self-start sm:self-center">
            Total Trashed: <span class="font-semibold text-foreground">{{ $totalItems }}</span>
        </div>
    </div>

    <!-- Tabs switcher -->
    <div class="flex items-center gap-2 border-b border-border">
        <a href="{{ route('studio.trash.index', ['tab' => 'galleries']) }}"
           class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors {{ $tab === 'galleries' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Galleries ({{ $galleries->total() }})
        </a>
        <a href="{{ route('studio.trash.index', ['tab' => 'photos']) }}"
           class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors {{ $tab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Individual Photos ({{ $photos->total() }})
        </a>
    </div>

    @if($tab === 'galleries')
        <!-- GALLERIES TABLE -->
        <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                        <tr>
                            <th class="px-6 py-4">Gallery Title</th>
                            <th class="px-6 py-4">Photos</th>
                            <th class="px-6 py-4">Deleted &amp; Retention</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($galleries as $gallery)
                            @php
                                $expiresAt = $gallery->deleted_at->copy()->addDays($retentionDays);
                                $hoursLeft = max(0, (int) now()->diffInHours($expiresAt, false));
                                $daysLeft = (int) ceil($hoursLeft / 24);
                            @endphp
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td class="px-6 py-4 font-semibold text-foreground">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-muted-foreground shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ $gallery->title }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs text-muted-foreground">
                                    {{ $gallery->photos_count ?? 0 }} photos
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <div class="text-muted-foreground">Deleted {{ $gallery->deleted_at->format('M j, Y g:i A') }}</div>
                                    <div class="mt-0.5">
                                        @if($daysLeft <= 0 || $hoursLeft < 24)
                                            <span class="inline-flex items-center gap-1 font-semibold text-destructive text-[11px]">
                                                ● Deletes permanently today
                                            </span>
                                        @elseif($daysLeft === 1)
                                            <span class="inline-flex items-center gap-1 font-semibold text-amber-500 text-[11px]">
                                                ● Deletes permanently tomorrow
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-muted-foreground text-[11px]">
                                                ● Deletes in {{ $daysLeft }} days
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Restore Gallery -->
                                        <form method="POST" action="{{ route('studio.trash.restore') }}">
                                            @csrf
                                            <input type="hidden" name="type" value="gallery">
                                            <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-secondary text-foreground hover:bg-border transition-colors">
                                                Restore
                                            </button>
                                        </form>

                                        <!-- Canonical POST: Purge Gallery -->
                                        <form method="POST" action="{{ route('studio.trash.purge') }}" onsubmit="return confirm('Permanently delete &quot;{{ addslashes($gallery->title) }}&quot;? All original files and image variants will be permanently deleted from B2 storage. This action cannot be undone.');">
                                            @csrf
                                            <input type="hidden" name="type" value="gallery">
                                            <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive hover:text-white transition-colors">
                                                Delete Permanently
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                    No galleries in trash.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pt-4">
            {{ $galleries->appends(['tab' => 'galleries'])->links() }}
        </div>
    @else
        <!-- PHOTOS TABLE -->
        <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                        <tr>
                            <th class="px-6 py-4">Photo</th>
                            <th class="px-6 py-4">Gallery</th>
                            <th class="px-6 py-4">Deleted &amp; Retention</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($photos as $photo)
                            @php
                                $expiresAt = $photo->deleted_at->copy()->addDays($retentionDays);
                                $hoursLeft = max(0, (int) now()->diffInHours($expiresAt, false));
                                $daysLeft = (int) ceil($hoursLeft / 24);
                            @endphp
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-muted overflow-hidden shrink-0 border border-border flex items-center justify-center">
                                            <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->filename }}" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <div style="display:none;" class="text-muted-foreground text-xs p-1 text-center">IMG</div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-foreground truncate max-w-[200px]" title="{{ $photo->original_filename ?: $photo->filename }}">
                                                {{ $photo->original_filename ?: $photo->filename }}
                                            </div>
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ number_format(($photo->size ?? 0) / (1024 * 1024), 2) }} MB
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs text-muted-foreground">
                                    {{ $photo->gallery?->title ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <div class="text-muted-foreground">Deleted {{ $photo->deleted_at->format('M j, Y g:i A') }}</div>
                                    <div class="mt-0.5">
                                        @if($daysLeft <= 0 || $hoursLeft < 24)
                                            <span class="inline-flex items-center gap-1 font-semibold text-destructive text-[11px]">
                                                ● Deletes permanently today
                                            </span>
                                        @elseif($daysLeft === 1)
                                            <span class="inline-flex items-center gap-1 font-semibold text-amber-500 text-[11px]">
                                                ● Deletes permanently tomorrow
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-muted-foreground text-[11px]">
                                                ● Deletes in {{ $daysLeft }} days
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Restore Photo -->
                                        <form method="POST" action="{{ route('studio.trash.restore') }}">
                                            @csrf
                                            <input type="hidden" name="type" value="photo">
                                            <input type="hidden" name="uuid" value="{{ $photo->uuid }}">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-secondary text-foreground hover:bg-border transition-colors">
                                                Restore
                                            </button>
                                        </form>

                                        <!-- Canonical POST: Purge Photo -->
                                        <form method="POST" action="{{ route('studio.trash.purge') }}" onsubmit="return confirm('Permanently delete this photo? Its original file and all generated image variants will be permanently deleted from B2 storage.');">
                                            @csrf
                                            <input type="hidden" name="type" value="photo">
                                            <input type="hidden" name="uuid" value="{{ $photo->uuid }}">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive hover:text-white transition-colors">
                                                Delete Permanently
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                    No individual photos in trash.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pt-4">
            {{ $photos->appends(['tab' => 'photos'])->links() }}
        </div>
    @endif

    <!-- Empty Trash Confirmation Modal -->
    <div x-show="emptyModalOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="emptyModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-2xl bg-card border border-border p-6 shadow-2xl text-left">
                <div class="flex items-center gap-3 text-destructive">
                    <div class="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-foreground">Empty All Trash?</h3>
                </div>

                <p class="text-xs text-muted-foreground mt-3 leading-relaxed">
                    Permanently delete all items in Trash? This will remove all galleries, photos, and generated variant files from B2 storage. This action cannot be undone.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" @click="emptyModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('studio.trash.empty') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-destructive text-white hover:opacity-90 shadow-sm transition-opacity">
                            Yes, Empty All Trash
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
