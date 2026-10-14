@extends('layouts.admin', ['title' => 'Analytics · Admin · ifotoset'])

@section('content')
@php
    $growth = collect($userGrowth);
    $totalSignups12 = $growth->sum('count');
    $thisMonth = (int) ($growth->last()['count'] ?? 0);
    $lastMonth = (int) ($growth->count() > 1 ? $growth[$growth->count() - 2]['count'] : 0);
    // Nice axis ceiling for the chart
    $rawMax = max(1, (int) $maxGrowth);
    $step = $rawMax <= 4 ? 1 : (int) ceil($rawMax / 4);
    $axisMax = $step * 4;
    $ticks = [$axisMax, $step * 3, $step * 2, $step, 0];
    $activationPct = $totalUsers > 0 ? round(($activeStudios / $totalUsers) * 100) : 0;
    $maxViews = max(1, (int) collect($topStudios)->max('views'));
@endphp

<x-ui.page-header eyebrow="Admin console" title="Analytics" description="Growth, adoption and the studios driving activity on the platform." />

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <x-studio.stat-card label="Total registrations" :value="number_format($totalUsers)" icon="users" hint="All accounts" />
    <x-studio.stat-card label="Active studios" :value="number_format($activeStudios)" icon="camera" :hint="$activationPct . '% of accounts have a gallery'" />
    <x-studio.stat-card label="Galleries delivered" :value="number_format($totalGalleries)" icon="images" hint="Across the platform" />
    <x-studio.stat-card label="Photo downloads" :value="number_format($totalDownloads)" icon="download" hint="By clients, all-time" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Signups chart --}}
    <div class="card lg:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">New registrations</h2>
                <p class="card-description">Monthly signups over the last 12 months.</p>
            </div>
            <div class="text-right">
                <div class="font-display text-xl font-semibold tabular-nums text-foreground">{{ number_format($totalSignups12) }}</div>
                <div class="text-xs text-muted-foreground">in 12 months</div>
            </div>
        </div>
        <div class="card-body">
            <figure aria-label="Bar chart of monthly user registrations">
                <div class="flex pl-7">
                    {{-- Plot --}}
                    <div class="relative h-56 flex-1">
                        <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between" aria-hidden="true">
                            @foreach($ticks as $t)
                                <div class="relative border-t {{ $loop->last ? 'border-border-strong' : 'border-dashed border-border' }}">
                                    <span class="absolute -left-7 top-0 w-5 -translate-y-1/2 text-right text-[11px] leading-none tabular-nums text-subtle">{{ $t }}</span>
                                </div>
                            @endforeach
                        </div>
                        <ol class="absolute inset-0 flex items-stretch gap-1 sm:gap-2">
                            @foreach($userGrowth as $m)
                                @php($pct = $axisMax > 0 ? ($m['count'] / $axisMax) * 100 : 0)
                                <li class="group relative flex flex-1 flex-col">
                                    <div class="relative flex flex-1 items-end justify-center">
                                        <div class="w-full max-w-9 rounded-t-[4px] transition-colors {{ $m['count'] > 0 ? 'bg-brand/80 group-hover:bg-brand' : 'bg-transparent' }} {{ $loop->last ? '!bg-brand' : '' }}"
                                             style="height: {{ $m['count'] > 0 ? max(2, $pct) : 0 }}%"></div>
                                        <div class="pointer-events-none absolute left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-md border border-border bg-popover px-2 py-1 text-[11px] shadow-md group-hover:block"
                                             style="bottom: calc({{ $pct }}% + 6px)">
                                            <span class="font-semibold tabular-nums text-foreground">{{ $m['count'] }}</span>
                                            <span class="text-muted-foreground">{{ \Illuminate\Support\Str::plural('signup', $m['count']) }} · {{ \Carbon\Carbon::createFromFormat('Y-m', $m['month'])->format('M Y') }}</span>
                                        </div>
                                    </div>
                                    <span class="flex h-6 items-end justify-center text-[10px] text-muted-foreground sm:text-[11px] {{ $loop->last ? 'font-medium text-foreground' : '' }}">
                                        <span class="sm:hidden">{{ mb_substr($m['label'], 0, 1) }}</span><span class="hidden sm:inline">{{ $m['label'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
                <figcaption class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-border pt-3 text-xs text-muted-foreground">
                    <span>This month <span class="font-medium tabular-nums text-foreground">{{ $thisMonth }}</span></span>
                    <span>Last month <span class="font-medium tabular-nums text-foreground">{{ $lastMonth }}</span></span>
                    <span>Peak month <span class="font-medium tabular-nums text-foreground">{{ (int) $maxGrowth }}</span></span>
                </figcaption>
            </figure>
            <details class="mt-3 text-xs">
                <summary class="cursor-pointer text-muted-foreground hover:text-foreground">View as table</summary>
                <div class="mt-2 grid grid-cols-3 gap-x-4 gap-y-1 sm:grid-cols-6">
                    @foreach($userGrowth as $m)
                        <div class="flex justify-between gap-2"><span class="text-muted-foreground">{{ $m['month'] }}</span><span class="tabular-nums text-foreground">{{ $m['count'] }}</span></div>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    {{-- Adoption --}}
    <div class="card flex flex-col">
        <div class="card-header">
            <div>
                <h2 class="card-title">Studio adoption</h2>
                <p class="card-description">Accounts that went on to deliver work.</p>
            </div>
        </div>
        <div class="card-body flex-1 space-y-5">
            <div>
                <div class="flex items-baseline justify-between">
                    <span class="stat-value">{{ $activationPct }}%</span>
                    <span class="text-xs text-muted-foreground">activation rate</span>
                </div>
                <div class="progress mt-3 !h-2"><span style="width: {{ $activationPct }}%"></span></div>
            </div>
            <dl class="space-y-3 text-[13px]">
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-muted-foreground"><x-icon name="users" class="size-4" /> Registered</dt>
                    <dd class="font-medium tabular-nums text-foreground">{{ number_format($totalUsers) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-muted-foreground"><x-icon name="camera" class="size-4" /> With a gallery</dt>
                    <dd class="font-medium tabular-nums text-foreground">{{ number_format($activeStudios) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-muted-foreground"><x-icon name="layers" class="size-4" /> Galleries per active studio</dt>
                    <dd class="font-medium tabular-nums text-foreground">{{ $activeStudios > 0 ? number_format($totalGalleries / $activeStudios, 1) : '0' }}</dd>
                </div>
            </dl>
            <div class="border-t border-border pt-4">
                <p class="eyebrow mb-3 !text-[11px]">Platform services</p>
                <ul class="space-y-2 text-[13px]">
                    <li class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Mobile Money</span><span class="badge badge-success badge-dot">MTN MoMo</span></li>
                    <li class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Edge delivery</span><span class="badge badge-neutral">Cloudflare + B2</span></li>
                    <li class="flex items-center justify-between gap-3"><span class="text-muted-foreground">ZIP archives</span><span class="badge badge-neutral">Direct stream</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Top studios --}}
<div class="card mt-6 overflow-hidden">
    <div class="card-header">
        <div>
            <h2 class="card-title">Top studios</h2>
            <p class="card-description">Most active photographer accounts.</p>
        </div>
    </div>
    @if(collect($topStudios)->isEmpty())
        <div class="p-5"><x-ui.empty-state title="No studio activity yet" description="Studios appear here once they create their first gallery." icon="camera" /></div>
    @else
        <div class="table-wrap relative">
            <table class="table">
                <thead>
                    <tr>
                        <th>Studio</th>
                        <th class="text-right">Galleries</th>
                        <th class="min-w-48">Gallery views</th>
                        <th class="text-right">Revenue</th>
                        <th class="w-px"><span class="sr-only">Portfolio</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topStudios as $s)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$s['name']" size="sm" />
                                    <div class="min-w-0">
                                        <div class="truncate text-[13px] font-medium text-foreground">{{ $s['name'] }}</div>
                                        <div class="truncate text-xs text-muted-foreground">{{ $s['username'] ? '@' . $s['username'] : 'No username' }} · since {{ $s['joined'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right tabular-nums text-[13px]">{{ $s['galleries_count'] }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="progress flex-1"><span style="width: {{ round(($s['views'] / $maxViews) * 100) }}%"></span></div>
                                    <span class="w-12 text-right text-[13px] tabular-nums">{{ number_format($s['views']) }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right text-[13px] font-medium tabular-nums">RWF {{ number_format($s['revenue'], 0) }}</td>
                            <td class="text-right">
                                @if($s['username'])
                                    <a href="{{ url('/p/' . $s['username']) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs whitespace-nowrap">
                                        View <x-icon name="arrow-up-right" />
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
