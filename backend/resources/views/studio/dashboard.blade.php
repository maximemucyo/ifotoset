@extends('layouts.app', ['title' => 'Dashboard - Studio'])

@section('content')
<div class="space-y-8">
    <!-- Welcome Header & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Welcome back, {{ $user->name }}
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Studio overview, client schedule, and cloud storage allocation.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if($user->username)
                <a href="{{ $user->public_url }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-secondary/80 text-secondary-foreground font-semibold text-xs border border-border hover:bg-secondary transition-colors">
                    <span>Portfolio</span>
                    <span class="text-xs">&nearr;</span>
                </a>
            @endif
            <a href="{{ route('studio.availability.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-card text-foreground font-semibold text-xs border border-border hover:border-primary transition-colors">
                Availability
            </a>
            <a href="{{ route('studio.galleries.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-primary-foreground font-semibold text-xs shadow-sm hover:opacity-90 transition-opacity">
                + New Gallery
            </a>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-studio.stat-card label="Total Galleries" :value="$totalGalleries" />
        <x-studio.stat-card label="Delivered Photos" :value="number_format($totalPhotos)" />
        <x-studio.stat-card label="Pending Bookings" :value="$pendingBookings" hint="Awaiting review" />
        
        <!-- Storage Card -->
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground">Storage Used</span>
                <span class="text-xs font-semibold text-primary">{{ round($storage['percentage'] ?? 0) }}%</span>
            </div>
            <div class="mt-3">
                <div class="text-xl font-bold text-foreground">
                    {{ round(($storage['used_bytes'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                    <span class="text-xs font-normal text-muted-foreground">/ {{ round(($storage['limit_bytes'] ?? (5 * 1024 * 1024 * 1024)) / (1024 * 1024 * 1024)) }} GB</span>
                </div>
                <div class="w-full bg-secondary h-2 rounded-full overflow-hidden mt-3">
                    <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ min(100, max(2, $storage['percentage'] ?? 0)) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Sessions & Schedule -->
    @if(isset($upcomingBookings) && $upcomingBookings->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-foreground">Upcoming Sessions</h2>
                <a href="{{ route('studio.bookings.index') }}" class="text-xs font-semibold text-primary hover:underline">
                    View all bookings &rarr;
                </a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($upcomingBookings as $booking)
                    <div class="rounded-2xl border border-border bg-card p-5 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-bold text-sm text-foreground">{{ $booking->client->name ?? 'Guest Client' }}</h3>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $booking->package->name ?? 'Photography Session' }}</p>
                                </div>
                                <x-ui.badge :variant="$booking->status === 'confirmed' ? 'success' : 'warning'">
                                    {{ ucfirst($booking->status) }}
                                </x-ui.badge>
                            </div>
                            <div class="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
                                <span class="font-medium text-foreground">{{ $booking->starts_at->format('M j, Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $booking->starts_at->format('g:i A') }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">{{ $booking->client->phone ?? $booking->client->email ?? '' }}</span>
                            <a href="{{ route('studio.bookings.index') }}" class="font-semibold text-primary hover:underline">Manage &rarr;</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Recent Galleries Section -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-foreground">Recent Galleries</h2>
            <a href="{{ route('studio.galleries.index') }}" class="text-xs font-semibold text-primary hover:underline">
                View all galleries &rarr;
            </a>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($recentGalleries as $gallery)
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

                    <div class="p-4 flex-1 flex flex-col justify-between">
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
                            @if($user->username)
                                <a href="{{ $gallery->public_url }}" target="_blank" class="text-muted-foreground hover:text-foreground">
                                    Preview &nearr;
                                </a>
                            @else
                                <span></span>
                            @endif
                            <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="font-semibold text-primary hover:underline">
                                Manage &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 rounded-2xl border border-dashed border-border p-8">
                    <p class="text-sm font-medium text-muted-foreground mb-4">You haven't created any client galleries yet.</p>
                    <a href="{{ route('studio.galleries.create') }}" class="px-4 py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-xl shadow-sm">
                        Create Your First Gallery
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
