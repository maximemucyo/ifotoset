@extends('layouts.admin', ['title' => 'Platform Analytics - Admin'])

@section('content')
<div class="space-y-8">
    <div class="pb-4 border-b border-border">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Platform Analytics &amp; Growth</h1>
        <p class="text-xs text-muted-foreground mt-1">High-level growth metrics, tenant adoption, and top performing creative studios.</p>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-studio.stat-card label="Total Registrations" :value="number_format($totalUsers)" hint="All accounts" />
        <x-studio.stat-card label="Active Studios" :value="number_format($activeStudios)" hint="With galleries" />
        <x-studio.stat-card label="Delivered Galleries" :value="number_format($totalGalleries)" hint="Across platform" />
        <x-studio.stat-card label="Total Photo Downloads" :value="number_format($totalDownloads)" hint="All clients" />
    </div>

    <!-- Growth Chart & Overview -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Monthly Signups Bar Chart -->
        <div class="lg:col-span-2 rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="font-bold text-base text-foreground">Photographer Adoption</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">New user registrations over the past 12 months</p>
                </div>
            </div>

            <div class="h-48 flex items-end gap-2 sm:gap-3 px-2 pt-4">
                @foreach($userGrowth as $m)
                    @php
                        $pct = $maxGrowth > 0 ? max(4, round(($m['count'] / $maxGrowth) * 100)) : 4;
                    @endphp
                    <div class="flex-1 flex flex-col items-center gap-2 group relative h-full justify-end">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-8 px-2 py-1 bg-foreground text-background text-[10px] font-bold rounded shadow-lg pointer-events-none whitespace-nowrap z-20">
                            {{ $m['count'] }} signups ({{ $m['month'] }})
                        </div>

                        <div class="w-full bg-gradient-to-t from-primary to-accent rounded-t-lg transition-all duration-300 group-hover:brightness-110"
                             style="height: {{ $pct }}%"></div>

                        <span class="text-[10px] font-medium text-muted-foreground">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Regional Adoption / Market Focus -->
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-base text-foreground">Market Focus</h3>
            <p class="text-xs text-muted-foreground leading-relaxed">
                Empowering Rwandan photographers, creative agencies, and regional media houses with high-performance client delivery.
            </p>

            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between p-3 rounded-xl bg-secondary/40 border border-border/50 text-xs">
                    <span class="font-medium text-foreground">Mobile Money Integration</span>
                    <span class="font-bold text-green-600">Active (MTN MoMo)</span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl bg-secondary/40 border border-border/50 text-xs">
                    <span class="font-medium text-foreground">Edge CDN Delivery</span>
                    <span class="font-bold text-primary">Cloudflare + B2</span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl bg-secondary/40 border border-border/50 text-xs">
                    <span class="font-medium text-foreground">ZIP Archive Streaming</span>
                    <span class="font-bold text-foreground">Direct Stream</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Studios -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="p-6 border-b border-border">
            <h3 class="font-bold text-base text-foreground">Top Performing Studios</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Most active photographer portfolios on ifotoset</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Studio / Photographer</th>
                        <th class="px-6 py-4">Galleries</th>
                        <th class="px-6 py-4">Views</th>
                        <th class="px-6 py-4">Revenue</th>
                        <th class="px-6 py-4 text-right">Portfolio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($topStudios as $s)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-foreground">{{ $s['name'] }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono">&#64;{{ $s['username'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs font-semibold text-foreground">
                                {{ $s['galleries_count'] }}
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-foreground">
                                {{ number_format($s['views']) }}
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-primary">
                                RWF {{ number_format($s['revenue'], 0) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($s['username'])
                                    <a href="{{ url('/p/' . $s['username']) }}" target="_blank" class="text-xs font-semibold text-primary hover:underline">
                                        View &nearr;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-muted-foreground">
                                No studio activity records yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
