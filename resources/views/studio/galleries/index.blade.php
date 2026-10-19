@extends('layouts.app', ['title' => 'Galleries · ifotoset'])

@section('content')
@php
    $currentVisibility = in_array(request('visibility'), ['public', 'private', 'password'], true) ? request('visibility') : 'all';
    $currentSort = request('sort', 'newest');
    $search = trim((string) request('q', ''));
    $visibilityCounts = $visibilityCounts ?? [];
    $totalCount = $totalCount ?? $galleries->total();
    $filters = [
        'all'      => ['label' => 'All',           'count' => $totalCount],
        'public'   => ['label' => 'Public',        'count' => $visibilityCounts['public'] ?? 0],
        'private'  => ['label' => 'Private',       'count' => $visibilityCounts['private'] ?? 0],
        'password' => ['label' => 'PIN protected', 'count' => $visibilityCounts['password'] ?? 0],
    ];
    $sorts = [
        'newest'  => 'Newest first',
        'oldest'  => 'Oldest first',
        'updated' => 'Recently updated',
        'event'   => 'Event date',
        'title'   => 'Title A–Z',
    ];
    $baseQuery = array_filter(['q' => $search ?: null, 'sort' => $currentSort !== 'newest' ? $currentSort : null]);
    $visMeta = [
        'public'   => ['label' => 'Public',        'icon' => 'globe', 'variant' => 'success'],
        'private'  => ['label' => 'Private',       'icon' => 'lock',  'variant' => 'neutral'],
        'password' => ['label' => 'PIN protected', 'icon' => 'key',   'variant' => 'warning'],
    ];
    $isFiltered = $search !== '' || $currentVisibility !== 'all';
@endphp

<x-ui.page-header title="Galleries" description="Manage, proof and deliver photo collections to your clients.">
    <x-slot:actions>
        <a href="{{ route('studio.trash.index') }}" class="btn btn-outline btn-sm"><x-icon name="trash-2" /> Trash</a>
    </x-slot:actions>
</x-ui.page-header>

<div x-data="{
        view: (() => { try { return localStorage.getItem('studio.galleries.view') || 'grid' } catch (e) { return 'grid' } })(),
        setView(v) { this.view = v; try { localStorage.setItem('studio.galleries.view', v) } catch (e) {} },
     }">
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="tabs max-w-full self-start overflow-x-auto" aria-label="Filter by visibility">
            @foreach($filters as $key => $f)
                <a href="{{ route('studio.galleries.index', $key === 'all' ? $baseQuery : array_merge($baseQuery, ['visibility' => $key])) }}"
                   class="tab whitespace-nowrap" @if($currentVisibility === $key) aria-selected="true" aria-current="page" @endif>
                    {{ $f['label'] }}
                    <span class="ml-1 text-[11px] tabular-nums text-muted-foreground">{{ $f['count'] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('studio.galleries.index') }}" class="flex flex-wrap items-center gap-2" x-ref="filterForm">
            @if($currentVisibility !== 'all')
                <input type="hidden" name="visibility" value="{{ $currentVisibility }}">
            @endif
            <div class="input-group w-full min-w-0 sm:w-64">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search title or client…" class="input h-9 text-[13px]" aria-label="Search galleries">
            </div>
            <label class="sr-only" for="gallery-sort">Sort by</label>
            <select id="gallery-sort" name="sort" class="select h-9 min-w-0 flex-1 text-[13px] sm:w-auto sm:flex-none" @change="$refs.filterForm.requestSubmit()">
                @foreach($sorts as $key => $label)
                    <option value="{{ $key }}" @selected($currentSort === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex h-9 items-center gap-0.5 rounded-lg border border-border bg-card p-0.5" role="group" aria-label="View">
                <button type="button" @click="setView('grid')" class="btn btn-ghost btn-icon btn-xs" :class="view === 'grid' ? 'bg-muted text-foreground' : 'text-muted-foreground'" :aria-pressed="view === 'grid'" aria-label="Grid view">
                    <x-icon name="layout-grid" />
                </button>
                <button type="button" @click="setView('list')" class="btn btn-ghost btn-icon btn-xs" :class="view === 'list' ? 'bg-muted text-foreground' : 'text-muted-foreground'" :aria-pressed="view === 'list'" aria-label="List view">
                    <x-icon name="list" />
                </button>
            </div>
            <noscript><button type="submit" class="btn btn-outline btn-sm">Apply</button></noscript>
        </form>
    </div>

    @if($search !== '')
        <div class="mt-4 flex items-center gap-2 text-[13px] text-muted-foreground">
            <span>{{ $galleries->total() }} {{ \Illuminate\Support\Str::plural('result', $galleries->total()) }} for <span class="font-medium text-foreground">“{{ $search }}”</span></span>
            <a href="{{ route('studio.galleries.index', array_filter(['visibility' => $currentVisibility !== 'all' ? $currentVisibility : null, 'sort' => $currentSort !== 'newest' ? $currentSort : null])) }}" class="btn btn-ghost btn-xs"><x-icon name="x" /> Clear</a>
        </div>
    @endif

    <div class="mt-6">
        @if($galleries->isEmpty())
            @if($isFiltered)
                <x-ui.empty-state icon="search" title="No galleries match"
                                  description="Try a different search term or visibility filter.">
                    <a href="{{ route('studio.galleries.index') }}" class="btn btn-outline btn-sm mt-5">Clear filters</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="images" title="Create your first gallery"
                                  description="Upload a shoot, choose who can see it and share a beautiful private link with your client."
                                  action-label="New gallery" :action-url="route('studio.galleries.create')" />
            @endif
        @else
            {{-- Grid view --}}
            <div x-show="view === 'grid'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5">
                @foreach($galleries as $gallery)
                    @include('studio.partials.gallery-card', ['gallery' => $gallery])
                @endforeach
            </div>

            {{-- List view --}}
            <div x-show="view === 'list'" x-cloak class="card overflow-hidden">
                <ul class="divide-y divide-border">
                    @foreach($galleries as $gallery)
                        @php
                            $vm = $visMeta[$gallery->visibility] ?? ['label' => ucfirst((string) $gallery->visibility), 'icon' => 'eye', 'variant' => 'neutral'];
                            $cover = $gallery->getCoverUrl('sm');
                            $publicUrl = filled($gallery->user?->username) ? $gallery->public_url : null;
                        @endphp
                        <li class="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/40">
                            <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="relative block aspect-[4/3] w-16 shrink-0 overflow-hidden rounded-md bg-muted sm:w-20">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105">
                                @else
                                    <span class="absolute inset-0 flex items-center justify-center text-muted-foreground"><x-icon name="images" /></span>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="block truncate text-sm font-medium text-foreground hover:underline hover:underline-offset-4">{{ $gallery->title }}</a>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ $gallery->client_name ?: 'No client' }}
                                    @if($gallery->event_date) <span class="text-subtle">&middot;</span> {{ $gallery->event_date->format('M j, Y') }} @endif
                                </p>
                            </div>
                            <div class="hidden w-32 shrink-0 md:block">
                                <x-ui.badge :variant="$vm['variant']"><x-icon :name="$vm['icon']" class="size-3" /> {{ $vm['label'] }}</x-ui.badge>
                            </div>
                            <div class="hidden w-20 shrink-0 text-right text-[13px] tabular-nums text-foreground sm:block">
                                {{ number_format($gallery->photo_count) }} <span class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::plural('photo', $gallery->photo_count) }}</span>
                            </div>
                            <div class="hidden w-28 shrink-0 text-right text-xs text-muted-foreground lg:block">{{ $gallery->updated_at->diffForHumans(short: true) }}</div>
                            <div class="flex shrink-0 items-center gap-0.5">
                                @if($publicUrl)
                                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Preview as client" title="Preview as client"><x-icon name="eye" /></a>
                                @endif
                                <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Gallery settings" title="Settings"><x-icon name="settings" /></a>
                                <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Manage photos" title="Manage photos"><x-icon name="chevron-right" /></a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if($galleries->hasPages())
                <div class="mt-8">
                    {{ $galleries->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
