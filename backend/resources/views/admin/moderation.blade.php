@extends('layouts.admin', ['title' => 'Content Moderation - Admin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Content Moderation &amp; Review</h1>
            <p class="text-xs text-muted-foreground mt-1">Review flagged collections, copyright inquiries, and approve galleries for public publication.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Queue: <span class="font-bold text-foreground">{{ $galleries->total() }}</span> collections
        </div>
    </div>

    <!-- Moderation Cards -->
    <div class="space-y-4">
        @forelse($galleries as $gallery)
            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-6 hover:shadow-md transition-shadow">
                <div class="flex items-start gap-4">
                    <div class="w-20 h-14 rounded-xl bg-muted shrink-0 overflow-hidden relative">
                        @if($gallery->getCoverUrl('sm'))
                            <img src="{{ $gallery->getCoverUrl('sm') }}" alt="{{ $gallery->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-[10px] text-muted-foreground">No cover</div>
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-base text-foreground">{{ $gallery->title }}</h3>
                            <x-ui.badge :variant="$gallery->visibility === 'password' ? 'warning' : 'muted'">
                                {{ ucfirst($gallery->visibility) }}
                            </x-ui.badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Created by <span class="font-medium text-foreground">{{ $gallery->user->name ?? 'Photographer' }}</span>
                            @if($gallery->user && $gallery->user->username)
                                (&commat;{{ $gallery->user->username }})
                            @endif
                            &bull; {{ $gallery->photo_count }} photos &bull; {{ $gallery->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if($gallery->user && $gallery->user->username)
                        <a href="{{ $gallery->public_url }}" target="_blank" class="px-3.5 py-1.5 rounded-xl border border-border bg-secondary/50 text-xs font-semibold text-foreground hover:bg-secondary transition-colors">
                            Inspect &nearr;
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.moderation.action', $gallery->uuid) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-green-600 text-white text-xs font-semibold hover:bg-green-700 shadow-sm transition-colors">
                            Approve &amp; Publish
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.moderation.action', $gallery->uuid) }}" onsubmit="return confirm('Reject this gallery?');">
                        @csrf
                        <input type="hidden" name="decision" value="reject">
                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-destructive/10 text-destructive text-xs font-semibold hover:bg-destructive/20 transition-colors">
                            Reject
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-center py-16 rounded-2xl border border-dashed border-border bg-card p-8">
                <p class="text-sm font-medium text-muted-foreground">Moderation queue is clean. All public galleries meet community guidelines.</p>
            </div>
        @endforelse
    </div>

    <div class="pt-4">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
