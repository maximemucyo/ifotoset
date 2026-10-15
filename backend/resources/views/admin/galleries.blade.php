@extends('layouts.admin', ['title' => 'Galleries · Admin · ifotoset'])

@section('content')
@php
    $filters = [
        ['label' => 'All', 'value' => null, 'icon' => null],
        ['label' => 'Public', 'value' => 'public', 'icon' => 'globe'],
        ['label' => 'PIN protected', 'value' => 'password', 'icon' => 'lock'],
        ['label' => 'Private', 'value' => 'private', 'icon' => 'eye-off'],
    ];
    $fmtBytes = function ($b) {
        $b = (float) $b; $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return ($i === 0 ? (int) $b : number_format($b, 1)) . ' ' . $u[$i];
    };
@endphp

<x-ui.page-header eyebrow="Admin console" title="Galleries" description="Every client gallery on the platform, with visibility and moderation controls.">
    <x-slot:meta>
        <p class="mt-3 text-xs text-muted-foreground"><span class="font-medium tabular-nums text-foreground">{{ number_format($galleries->total()) }}</span> {{ \Illuminate\Support\Str::plural('gallery', $galleries->total()) }}{{ $search || $visibility ? ' match these filters' : ' in total' }}</p>
    </x-slot:meta>
    <x-slot:actions>
        <a href="{{ route('admin.moderation.index') }}" class="btn btn-outline btn-sm"><x-icon name="flag" /> Moderation queue</a>
    </x-slot:actions>
</x-ui.page-header>

<div class="card overflow-hidden">
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 border-b border-border p-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="tabs max-w-full overflow-x-auto" role="tablist" aria-label="Filter by visibility">
            @foreach($filters as $f)
                <a href="{{ route('admin.galleries.index', array_filter(['search' => $search, 'visibility' => $f['value']])) }}"
                   role="tab" aria-selected="{{ ($visibility ?: null) === $f['value'] ? 'true' : 'false' }}" class="tab whitespace-nowrap">
                    @if($f['icon'])<x-icon :name="$f['icon']" />@endif
                    {{ $f['label'] }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.galleries.index') }}" class="w-full sm:w-72">
            @if($visibility)<input type="hidden" name="visibility" value="{{ $visibility }}">@endif
            <div class="input-group">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ $search }}" placeholder="Search title, slug or photographer" aria-label="Search galleries" class="input input-sm pr-8">
                @if($search)
                    <a href="{{ route('admin.galleries.index', array_filter(['visibility' => $visibility])) }}" class="absolute right-2 flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Clear search">
                        <x-icon name="x" class="size-3.5" />
                    </a>
                @endif
            </div>
        </form>
    </div>

    @if($galleries->isEmpty())
        <div class="p-5">
            <x-ui.empty-state title="No galleries found" description="No galleries match this search or filter." icon="images">
                @if($search || $visibility)
                    <a href="{{ route('admin.galleries.index') }}" class="btn btn-outline btn-sm mt-5"><x-icon name="rotate-ccw" /> Clear filters</a>
                @endif
            </x-ui.empty-state>
        </div>
    @else
        <div class="table-wrap relative">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gallery</th>
                        <th class="hidden md:table-cell">Photographer</th>
                        <th>Status</th>
                        <th class="hidden lg:table-cell text-right">Photos</th>
                        <th class="hidden xl:table-cell text-right">Size</th>
                        <th class="hidden lg:table-cell">Created</th>
                        <th class="w-px text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($galleries as $gallery)
                        @php($cover = $gallery->getCoverUrl('sm'))
                        <tr class="{{ $gallery->isTakenDown() ? 'bg-destructive/[0.03]' : '' }}">
                            <td class="max-w-[10.5rem] sm:max-w-none">
                                <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}" class="group flex min-w-0 items-center gap-3">
                                    <span class="relative flex aspect-[4/3] w-14 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted text-muted-foreground">
                                        @if($cover)
                                            <img src="{{ $cover }}" alt="" class="size-full object-cover {{ $gallery->isTakenDown() ? 'opacity-40 grayscale' : '' }}" loading="lazy" decoding="async" width="56" height="42">
                                        @else
                                            <x-icon name="image" class="size-4" />
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13px] font-medium text-foreground group-hover:underline group-hover:underline-offset-4">{{ $gallery->title }}</span>
                                        <span class="block truncate font-mono text-[11px] text-muted-foreground">/g/{{ $gallery->user?->username }}/{{ $gallery->slug }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="hidden md:table-cell">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-ui.avatar :name="$gallery->user->name ?? 'Unknown'" size="xs" />
                                    <div class="min-w-0">
                                        <div class="truncate text-[13px] text-foreground">{{ $gallery->user->name ?? 'Unknown' }}</div>
                                        @if($gallery->user && $gallery->user->username)
                                            <div class="truncate text-[11px] text-muted-foreground">{{ '@' . $gallery->user->username }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if($gallery->visibility === 'public')
                                        <span class="badge badge-success"><x-icon name="globe" /> Public</span>
                                    @elseif($gallery->visibility === 'password')
                                        <span class="badge badge-warning"><x-icon name="lock" /> PIN</span>
                                    @else
                                        <span class="badge badge-neutral"><x-icon name="eye-off" /> Private</span>
                                    @endif
                                    @if($gallery->isTakenDown())
                                        <span class="badge badge-danger" title="{{ $gallery->takedown_reason }}"><x-icon name="ban" /> Taken down</span>
                                    @endif
                                </div>
                            </td>
                            <td class="hidden lg:table-cell text-right text-[13px] tabular-nums">{{ number_format($gallery->photo_count) }}</td>
                            <td class="hidden xl:table-cell text-right text-[13px] tabular-nums text-muted-foreground">{{ $fmtBytes($gallery->stats?->total_bytes ?? 0) }}</td>
                            <td class="hidden lg:table-cell whitespace-nowrap text-[13px] text-muted-foreground">{{ $gallery->created_at->format('M j, Y') }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1"
                                     x-data="{ menuOpen: false, takedownOpen: false, reason: '', menuTop: 0, menuLeft: 0,
                                               openMenu() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuTop = r.bottom + 6; this.menuLeft = Math.max(8, r.right - 224); this.menuOpen = !this.menuOpen; } }"
                                     @keydown.escape.window="menuOpen = false; takedownOpen = false">
                                    <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}" class="btn btn-outline btn-xs hidden sm:inline-flex" title="Preview as admin (bypasses PIN and invitations)">
                                        <x-icon name="shield" /> Preview
                                    </a>
                                    <button type="button" x-ref="trigger" @click="openMenu()" class="btn btn-ghost btn-xs btn-icon" :class="menuOpen && 'bg-muted text-foreground'" aria-label="More actions for {{ $gallery->title }}" aria-haspopup="menu" :aria-expanded="menuOpen">
                                        <x-icon name="ellipsis" />
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="menuOpen" x-cloak
                                             @click.outside="if (! $refs.trigger.contains($event.target)) menuOpen = false"
                                             @scroll.window.capture="menuOpen = false" @resize.window="menuOpen = false"
                                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                             :style="`top:${menuTop}px;left:${menuLeft}px`"
                                             class="menu fixed w-56 origin-top-right text-left" role="menu">
                                            <div class="menu-label truncate">{{ $gallery->title }}</div>
                                            <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}" class="menu-item" role="menuitem"><x-icon name="shield" /> Admin preview</a>
                                            @if($gallery->user && $gallery->user->username)
                                                <a href="{{ $gallery->public_url }}" target="_blank" rel="noopener" class="menu-item" role="menuitem"><x-icon name="globe" /> Public page <x-icon name="arrow-up-right" class="ml-auto !size-3.5" /></a>
                                            @endif
                                            <div class="menu-separator"></div>
                                            <form method="POST" action="{{ route('admin.galleries.visibility', $gallery->uuid) }}">
                                                @csrf
                                                <button type="submit" class="menu-item" role="menuitem">
                                                    <x-icon :name="$gallery->visibility === 'public' ? 'eye-off' : 'eye'" />
                                                    {{ $gallery->visibility === 'public' ? 'Make Private' : 'Make Public' }}
                                                </button>
                                            </form>
                                            <div class="menu-separator"></div>
                                            @if($gallery->isTakenDown())
                                                <form method="POST" action="{{ route('admin.galleries.restore', $gallery->uuid) }}" onsubmit="return confirm('Restore gallery {{ addslashes($gallery->title) }}?');">
                                                    @csrf
                                                    <button type="submit" class="menu-item" role="menuitem"><x-icon name="rotate-ccw" /> Restore gallery</button>
                                                </form>
                                            @else
                                                <button type="button" class="menu-item menu-item-danger" role="menuitem" @click="menuOpen = false; takedownOpen = true">
                                                    <x-icon name="ban" /> Take down…
                                                </button>
                                            @endif
                                        </div>
                                    </template>

                                    @unless($gallery->isTakenDown())
                                        <template x-teleport="body">
                                            <div x-show="takedownOpen" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
                                                <div x-show="takedownOpen" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="takedownOpen = false"></div>
                                                <div x-show="takedownOpen"
                                                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                                     class="relative w-full max-w-md rounded-2xl border border-border bg-card text-left shadow-2xl">
                                                    <form method="POST" action="{{ route('admin.galleries.takedown', $gallery->uuid) }}">
                                                        @csrf
                                                        <div class="space-y-4 p-5">
                                                            <div class="flex gap-3">
                                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"><x-icon name="ban" class="size-4" /></span>
                                                                <div>
                                                                    <h3 class="text-[15px] font-semibold text-foreground">Take down this gallery?</h3>
                                                                    <p class="mt-1 text-[13px] text-muted-foreground"><span class="font-medium text-foreground">{{ $gallery->title }}</span> will show a moderation notice to visitors. The photographer's own visibility settings are kept for a later restore.</p>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="label" for="reason-{{ $gallery->uuid }}">Reason <span class="text-destructive">*</span></label>
                                                                <textarea id="reason-{{ $gallery->uuid }}" name="reason" x-model="reason" rows="3" required minlength="3" maxlength="500" class="textarea" placeholder="e.g. Copyright notice, Terms of Service violation…"></textarea>
                                                                <p class="help">Recorded in the audit log.</p>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer">
                                                            <button type="button" @click="takedownOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                                                            <button type="submit" :disabled="reason.trim().length < 3" class="btn btn-danger btn-sm">Take down</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($galleries->hasPages())
        <div class="border-t border-border px-4 py-3">
            {{ $galleries->links() }}
        </div>
    @endif
</div>
@endsection
