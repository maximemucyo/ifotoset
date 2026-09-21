@extends('layouts.admin', ['title' => 'Galleries Moderation - Admin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Galleries Moderation</h1>
            <p class="text-xs text-muted-foreground mt-1">Platform-wide overview of all photographer client galleries and visibility controls.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Total Galleries: <span class="font-bold text-foreground">{{ $galleries->total() }}</span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" action="{{ route('admin.galleries.index') }}" class="w-full sm:w-80">
            <div class="relative">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by title, photographer, slug..."
                       class="w-full rounded-xl border border-border bg-card px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                @if($search)
                    <a href="{{ route('admin.galleries.index', array_filter(['visibility' => $visibility])) }}" class="absolute right-3 top-2.5 text-xs text-muted-foreground hover:text-foreground">
                        &times;
                    </a>
                @endif
            </div>
        </form>

        <div class="flex items-center gap-2 text-xs font-medium">
            <a href="{{ route('admin.galleries.index', array_filter(['search' => $search])) }}"
               class="px-3 py-1.5 rounded-lg {{ empty($visibility) ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                All
            </a>
            <a href="{{ route('admin.galleries.index', array_filter(['search' => $search, 'visibility' => 'public'])) }}"
               class="px-3 py-1.5 rounded-lg {{ $visibility === 'public' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                Public
            </a>
            <a href="{{ route('admin.galleries.index', array_filter(['search' => $search, 'visibility' => 'password'])) }}"
               class="px-3 py-1.5 rounded-lg {{ $visibility === 'password' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                PIN Protected
            </a>
            <a href="{{ route('admin.galleries.index', array_filter(['search' => $search, 'visibility' => 'private'])) }}"
               class="px-3 py-1.5 rounded-lg {{ $visibility === 'private' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                Private
            </a>
        </div>
    </div>

    <!-- Galleries Table -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Gallery</th>
                        <th class="px-6 py-4">Photographer</th>
                        <th class="px-6 py-4">Visibility</th>
                        <th class="px-6 py-4">Moderation</th>
                        <th class="px-6 py-4">Photos</th>
                        <th class="px-6 py-4">Created</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($galleries as $gallery)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-foreground">{{ $gallery->title }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono mt-0.5">/g/{{ $gallery->slug }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="text-foreground font-medium">{{ $gallery->user->name ?? 'Unknown' }}</div>
                                @if($gallery->user && $gallery->user->username)
                                    <div class="text-muted-foreground font-mono">@<span>{{ $gallery->user->username }}</span></div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="$gallery->visibility === 'public' ? 'default' : ($gallery->visibility === 'password' ? 'warning' : 'muted')">
                                    {{ ucfirst($gallery->visibility) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4">
                                @if($gallery->isTakenDown())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-destructive/10 text-destructive uppercase">
                                        Taken Down
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/10 text-green-600 uppercase">
                                        Normal
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-foreground font-semibold">
                                {{ $gallery->photo_count }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $gallery->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <!-- Explicit Admin Preview (Bypasses Passwords/Invites) -->
                                    <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}"
                                       class="font-bold text-primary hover:underline flex items-center gap-1">
                                        <span>🛡 Preview</span>
                                        <span>&nearr;</span>
                                    </a>

                                    <!-- Toggle Visibility -->
                                    <form method="POST" action="{{ route('admin.galleries.visibility', $gallery->uuid) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="font-medium text-muted-foreground hover:text-foreground cursor-pointer">
                                            {{ $gallery->visibility === 'public' ? 'Make Private' : 'Make Public' }}
                                        </button>
                                    </form>

                                    <!-- Takedown or Restore -->
                                    @if($gallery->isTakenDown())
                                        <form method="POST" action="{{ route('admin.galleries.restore', $gallery->uuid) }}" onsubmit="return confirm('Restore gallery {{ addslashes($gallery->title) }}?');">
                                            @csrf
                                            <button type="submit" class="font-bold text-green-600 hover:underline cursor-pointer">
                                                Restore
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.galleries.takedown', $gallery->uuid) }}" onsubmit="const reason = prompt('Please specify a reason for taking down {{ addslashes($gallery->title) }}:'); if (!reason || !reason.trim()) return false; this.querySelector('input[name=reason]').value = reason; return true;">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button type="submit" class="font-medium text-destructive hover:underline cursor-pointer">
                                                Take Down
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                No galleries found matching this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
