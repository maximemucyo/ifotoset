@extends('layouts.app', ['title' => 'Galleries - Studio'])

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Client Galleries</h1>
            <p class="text-xs text-muted-foreground mt-1">Manage, proof, and deliver photo collections to your clients.</p>
        </div>

        <a href="{{ route('studio.galleries.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-primary-foreground font-semibold text-sm shadow-sm hover:opacity-90 transition-opacity">
            + New Gallery
        </a>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 border-b border-border pb-4 text-xs font-medium">
        <a href="{{ route('studio.galleries.index') }}"
           class="px-3 py-1.5 rounded-lg {{ !request('visibility') ? 'bg-secondary text-foreground font-semibold' : 'text-muted-foreground hover:text-foreground' }}">
            All Galleries
        </a>
        <a href="{{ route('studio.galleries.index', ['visibility' => 'public']) }}"
           class="px-3 py-1.5 rounded-lg {{ request('visibility') === 'public' ? 'bg-secondary text-foreground font-semibold' : 'text-muted-foreground hover:text-foreground' }}">
            Public
        </a>
        <a href="{{ route('studio.galleries.index', ['visibility' => 'password']) }}"
           class="px-3 py-1.5 rounded-lg {{ request('visibility') === 'password' ? 'bg-secondary text-foreground font-semibold' : 'text-muted-foreground hover:text-foreground' }}">
            PIN Protected
        </a>
        <a href="{{ route('studio.galleries.index', ['visibility' => 'private']) }}"
           class="px-3 py-1.5 rounded-lg {{ request('visibility') === 'private' ? 'bg-secondary text-foreground font-semibold' : 'text-muted-foreground hover:text-foreground' }}">
            Private
        </a>
    </div>

    <!-- Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($galleries as $gallery)
            @php
                $coverUrl = $gallery->getCoverUrl('md');
            @endphp
            <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
                <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="block aspect-[16/10] bg-muted relative overflow-hidden">
                    @if($coverUrl)
                        <img src="{{ $coverUrl }}" alt="{{ $gallery->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-muted-foreground text-xs">
                            No cover photo
                        </div>
                    @endif
                    <div class="absolute top-3 right-3">
                        <x-ui.badge :variant="$gallery->visibility === 'public' ? 'default' : ($gallery->visibility === 'password' ? 'warning' : 'muted')">
                            {{ ucfirst($gallery->visibility) }}
                        </x-ui.badge>
                    </div>
                </a>

                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-base text-foreground truncate">
                            <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="hover:text-primary transition-colors">
                                {{ $gallery->title }}
                            </a>
                        </h3>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ $gallery->photo_count }} photos &bull; Updated {{ $gallery->updated_at->diffForHumans() }}
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs">
                        <a href="{{ $gallery->public_url }}" target="_blank" class="text-muted-foreground hover:text-foreground">
                            Preview &nearr;
                        </a>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="text-muted-foreground hover:text-foreground">
                                Settings
                            </a>
                            <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="font-semibold text-primary hover:underline">
                                Photos &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-16 rounded-2xl border border-dashed border-border p-8">
                <p class="text-sm font-medium text-muted-foreground mb-4">No galleries found matching this filter.</p>
                <a href="{{ route('studio.galleries.create') }}" class="px-4 py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-lg shadow-sm">
                    Create New Gallery
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-4">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
