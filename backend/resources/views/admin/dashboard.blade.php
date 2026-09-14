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
