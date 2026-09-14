@extends('layouts.app', ['title' => 'Analytics - Studio'])

@section('content')
<div class="space-y-8">
    <!-- Header & Period Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Studio Analytics</h1>
            <p class="text-xs text-muted-foreground mt-1">Track visitor traffic, photo downloads, and client engagement metrics.</p>
        </div>

        <div class="flex items-center gap-2">
            @foreach(['7d' => 'Last 7 Days', '30d' => 'Last 30 Days', '90d' => 'Last 90 Days'] as $key => $label)
                <a href="{{ route('studio.analytics.index', ['period' => $key]) }}"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $period === $key ? 'bg-secondary text-foreground shadow-sm' : 'bg-card border border-border text-muted-foreground hover:text-foreground' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-studio.stat-card label="Gallery Views"
                            :value="number_format($totalViews)"
                            :change="($viewsChange >= 0 ? '+' : '') . $viewsChange . '% vs prev'"
                            :changeType="$viewsChange >= 0 ? 'positive' : 'negative'" />

        <x-studio.stat-card label="Photo Downloads"
                            :value="number_format($totalDownloads)"
                            :change="($downloadsChange >= 0 ? '+' : '') . $downloadsChange . '% vs prev'"
                            :changeType="$downloadsChange >= 0 ? 'positive' : 'negative'" />

        <x-studio.stat-card label="Unique Visitors"
                            :value="number_format($uniqueVisitors)"
                            hint="Tracked sessions" />

        <x-studio.stat-card label="Gallery Shares"
                            :value="number_format($galleryShares)"
                            hint="Shared via link" />
    </div>

    <!-- Monthly Views Chart & Distribution -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Monthly Views Bar Chart -->
        <div class="lg:col-span-2 rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="font-bold text-base text-foreground">Visitor Traffic Trend</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Monthly gallery views over the past 12 months</p>
                </div>
            </div>

            <div class="h-48 flex items-end gap-2 sm:gap-3 px-2 pt-4">
                @foreach($monthlyViews as $m)
                    @php
                        $pct = $maxMonthlyView > 0 ? max(4, round(($m['count'] / $maxMonthlyView) * 100)) : 4;
                    @endphp
                    <div class="flex-1 flex flex-col items-center gap-2 group relative h-full justify-end">
                        <!-- Tooltip -->
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-8 px-2 py-1 bg-foreground text-background text-[10px] font-bold rounded shadow-lg pointer-events-none whitespace-nowrap z-20">
                            {{ $m['count'] }} views ({{ $m['month'] }})
                        </div>

                        <!-- Bar -->
                        <div class="w-full bg-gradient-to-t from-primary to-accent rounded-t-lg transition-all duration-300 group-hover:brightness-110"
                             style="height: {{ $pct }}%"></div>

                        <!-- Month Label -->
                        <span class="text-[10px] font-medium text-muted-foreground">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Conversion Highlights -->
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-5">
            <h3 class="font-bold text-base text-foreground">Performance Summary</h3>

            <div class="space-y-4">
                <div class="p-3.5 rounded-xl bg-secondary/40 border border-border/50">
                    <div class="text-xs text-muted-foreground">Average Views Per Gallery</div>
                    <div class="text-xl font-bold text-foreground mt-1">
                        {{ $user->galleries()->count() > 0 ? round($totalViews / max(1, $user->galleries()->count()), 1) : 0 }}
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-secondary/40 border border-border/50">
                    <div class="text-xs text-muted-foreground">Download-to-View Ratio</div>
                    <div class="text-xl font-bold text-primary mt-1">
                        {{ $totalViews > 0 ? round(($totalDownloads / $totalViews) * 100, 1) : 0 }}%
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-secondary/40 border border-border/50">
                    <div class="text-xs text-muted-foreground">Client Engagement</div>
                    <div class="text-sm font-semibold text-foreground mt-1">
                        {{ $totalDownloads > 0 ? 'High Activity' : 'Standard' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Galleries -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="p-6 border-b border-border">
            <h3 class="font-bold text-base text-foreground">Top Performing Client Galleries</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Most engaged collections by view count in this period</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Gallery Title</th>
                        <th class="px-6 py-4">Views</th>
                        <th class="px-6 py-4">Downloads</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($topGalleries as $g)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4 font-semibold text-foreground">
                                {{ $g->title }}
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-foreground">
                                {{ number_format($g->views_count) }}
                            </td>
                            <td class="px-6 py-4 text-xs text-primary font-bold">
                                {{ number_format($g->downloads_count) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    @if($user->username)
                                        <a href="{{ url('/p/' . $user->username . '/' . $g->slug) }}" target="_blank" class="text-muted-foreground hover:text-foreground">
                                            Preview &nearr;
                                        </a>
                                    @endif
                                    <a href="{{ route('studio.galleries.show', $g->uuid) }}" class="font-semibold text-primary hover:underline">
                                        Manage &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                No activity recorded yet for this period. Share your gallery links with clients to generate views.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
