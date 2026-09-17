@extends('layouts.admin', ['title' => 'Admin Dashboard - ifotoset'])

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Platform Overview</h1>
        <p class="text-xs text-muted-foreground mt-1">Global platform metrics, revenue, and recent tenants.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-studio.stat-card label="Total Photographers" :value="number_format($totalUsers)" />
        <x-studio.stat-card label="Active Galleries" :value="number_format($totalGalleries)" />
        <x-studio.stat-card label="Storage Allocated" :value="round($totalStorageBytes / (1024 * 1024 * 1024), 2) . ' GB'" />
        <x-studio.stat-card label="Completed Revenue" :value="'$' . number_format($totalRevenue, 2)" />
    </div>

    <!-- Processing Queue Operational Quick Status -->
    <div class="p-5 rounded-2xl border border-border bg-card shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 {{ ($queueSummary['processing'] ?? 0) > 0 ? 'animate-spin' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <span>Background Processing Queue</span>
                    @if(($queueSummary['processing'] ?? 0) > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-primary/10 text-primary animate-pulse">
                            Active
                        </span>
                    @endif
                </h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    <span class="font-semibold text-foreground">{{ $queueSummary['processing'] ?? 0 }}</span> Processing &bull;
                    <span class="font-semibold text-foreground">{{ $queueSummary['queued'] ?? 0 }}</span> Queued &bull;
                    <span class="font-semibold text-destructive">{{ $queueSummary['failed_today'] ?? 0 }}</span> Failed Today
                </p>
            </div>
        </div>
        <a href="{{ route('admin.jobs.index') }}"
           class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold bg-secondary hover:bg-secondary/80 text-foreground border border-border transition-colors">
            View Processing Queue &rarr;
        </a>
    </div>

    <!-- Recent Users and Galleries Split -->
    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Users -->
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-foreground">Recently Registered Users</h2>
                <a href="{{ route('admin.users.index') }}" class="text-xs text-primary font-semibold hover:underline">View all</a>
            </div>
            <div class="divide-y divide-border">
                @foreach($recentUsers as $u)
                    <div class="py-3 flex items-center justify-between text-xs">
                        <div>
                            <div class="font-semibold text-foreground">{{ $u->name }}</div>
                            <div class="text-muted-foreground">{{ $u->email }} &bull; @<span>{{ $u->username }}</span></div>
                        </div>
                        <span class="text-muted-foreground">{{ $u->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Galleries -->
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-foreground">Recent Galleries</h2>
                <a href="{{ route('admin.galleries.index') }}" class="text-xs text-primary font-semibold hover:underline">View all</a>
            </div>
            <div class="divide-y divide-border">
                @foreach($recentGalleries as $g)
                    <div class="py-3 flex items-center justify-between text-xs">
                        <div>
                            <div class="font-semibold text-foreground">{{ $g->title }}</div>
                            <div class="text-muted-foreground">by {{ $g->user->name ?? 'Photographer' }}</div>
                        </div>
                        <x-ui.badge :variant="$g->visibility === 'public' ? 'default' : 'muted'">
                            {{ ucfirst($g->visibility) }}
                        </x-ui.badge>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
