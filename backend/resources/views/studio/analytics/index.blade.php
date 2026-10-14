@extends('layouts.app', ['title' => 'Analytics · ifotoset'])

@section('content')
@php
    $periods = ['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'];
    $periodLabel = ['7d' => 'last 7 days', '30d' => 'last 30 days', '90d' => 'last 90 days'][$period] ?? 'last 30 days';
    $galleryCount = $galleryCount ?? $user->galleries()->count();

    $fmtChange = fn ($v) => ($v > 0 ? '+' : '') . rtrim(rtrim(number_format($v, 1), '0'), '.') . '% vs prev.';
    $changeType = fn ($v) => $v > 0 ? 'positive' : ($v < 0 ? 'negative' : 'neutral');

    // ---- Chart geometry -------------------------------------------------
    $chartMax = max(1, (int) $maxMonthlyView);
    // Round the axis up to 4 "nice" steps (1, 2, 2.5, 5 × 10^k) so gridlines land on clean values.
    $rawStep = $chartMax / 4;
    $pow = 10 ** floor(log10(max(1, $rawStep)));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $pow)->first(fn ($v) => $v >= $rawStep && floor($v) == $v) ?? 10 * $pow;
    $step = max(1, (int) $step);
    $niceMax = $step * 4;
    $ticks = [$niceMax, $step * 3, $step * 2, $step, 0];
    $yearTotal = array_sum(array_column($monthlyViews, 'count'));
    $currentMonth = now()->format('Y-m');

    $avgViews = $galleryCount > 0 ? round($totalViews / max(1, $galleryCount), 1) : 0;
    $dlRatio = $totalViews > 0 ? round(($totalDownloads / $totalViews) * 100, 1) : 0;
    $topGalleries = collect($topGalleries);
    $topMax = max(1, (int) $topGalleries->max('views_count'));
@endphp

<x-ui.page-header title="Analytics" description="Visitor traffic, downloads and client engagement across your galleries.">
    <x-slot:actions>
        <nav class="tabs" aria-label="Period">
            @foreach($periods as $key => $label)
                <a href="{{ route('studio.analytics.index', ['period' => $key]) }}" class="tab" @if($period === $key) aria-selected="true" aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot:actions>
</x-ui.page-header>

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-studio.stat-card label="Gallery views" icon="eye"
                        :value="number_format($totalViews)"
                        :change="$viewsChange != 0 ? $fmtChange($viewsChange) : null"
                        :changeType="$changeType($viewsChange)"
                        :hint="$viewsChange == 0 ? 'In the ' . $periodLabel : null" />
    <x-studio.stat-card label="Photo downloads" icon="download"
                        :value="number_format($totalDownloads)"
                        :change="$downloadsChange != 0 ? $fmtChange($downloadsChange) : null"
                        :changeType="$changeType($downloadsChange)"
                        :hint="$downloadsChange == 0 ? 'In the ' . $periodLabel : null" />
    <x-studio.stat-card label="Unique visitors" icon="users" :value="number_format($uniqueVisitors)" hint="Distinct visitor sessions" />
    <x-studio.stat-card label="Gallery shares" icon="share-2" :value="number_format($galleryShares)" hint="Links shared by visitors" />
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-3 lg:gap-6">
    {{-- Monthly views chart --}}
    <section class="card p-5 sm:p-6 lg:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold text-foreground">Gallery views</h2>
                <p class="mt-0.5 text-[13px] text-muted-foreground">Monthly views over the past 12 months</p>
            </div>
            <div class="sm:text-right">
                <p class="font-display text-xl font-semibold tabular-nums text-foreground">{{ number_format($yearTotal) }}</p>
                <p class="text-xs text-muted-foreground">views in 12 months</p>
            </div>
        </div>

        <div class="mt-6 flex gap-3" role="img" aria-label="Bar chart of monthly gallery views for the past 12 months, totalling {{ number_format($yearTotal) }} views">
            {{-- Y axis --}}
            <div class="relative h-52 w-8 shrink-0 text-[11px] tabular-nums text-subtle" aria-hidden="true">
                <div class="absolute inset-x-0 top-0 bottom-6">
                    @foreach($ticks as $i => $t)
                        <span class="absolute right-0 -translate-y-1/2 leading-none" style="top: {{ $i * 25 }}%">{{ number_format($t) }}</span>
                    @endforeach
                </div>
            </div>

            <div class="relative h-52 min-w-0 flex-1">
                {{-- Grid --}}
                <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between" aria-hidden="true">
                    @foreach($ticks as $i => $t)
                        <div class="border-t {{ $loop->last ? 'border-border-strong' : 'border-dashed border-border' }}"></div>
                    @endforeach
                </div>

                {{-- Bars --}}
                <div class="absolute inset-0 flex items-stretch gap-1 sm:gap-2">
                    @foreach($monthlyViews as $m)
                        @php
                            $pct = $m['count'] > 0 ? max(1.5, ($m['count'] / $niceMax) * 100) : 0;
                            $isCurrent = $m['month'] === $currentMonth;
                            $monthName = \Carbon\Carbon::createFromFormat('Y-m', $m['month'])->format('F Y');
                        @endphp
                        <div class="group relative flex min-w-0 flex-1 flex-col" tabindex="0" aria-label="{{ $monthName }}: {{ number_format($m['count']) }} views">
                            <div class="relative flex flex-1 items-end justify-center rounded-md transition-colors group-hover:bg-muted/60 group-focus:bg-muted/60">
                                <div class="w-full max-w-7 rounded-t-[4px] transition-[filter,opacity] duration-200 {{ $isCurrent ? 'bg-brand' : 'bg-foreground/80 dark:bg-foreground/70' }} group-hover:brightness-110"
                                     style="height: {{ $pct }}%"></div>
                                {{-- Tooltip --}}
                                <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-lg border border-border bg-popover px-2.5 py-1.5 text-left opacity-0 shadow-md transition duration-150 group-hover:translate-y-0 group-hover:opacity-100 group-focus:translate-y-0 group-focus:opacity-100"
                                     style="bottom: {{ $pct }}%">
                                    <p class="text-[11px] text-muted-foreground">{{ $monthName }}</p>
                                    <p class="text-[13px] font-semibold tabular-nums text-foreground">{{ number_format($m['count']) }} <span class="font-normal text-muted-foreground">views</span></p>
                                </div>
                            </div>
                            <span class="mt-2 h-4 overflow-hidden text-center text-[10px] font-medium sm:text-[11px] {{ $isCurrent ? 'text-foreground' : 'text-muted-foreground' }} {{ ($loop->remaining % 2 === 1 && ! $isCurrent) ? 'max-sm:invisible' : '' }}">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                @if($yearTotal === 0)
                    <div class="absolute inset-x-0 top-0 bottom-6 flex items-center justify-center">
                        <p class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground shadow-xs">No views recorded yet</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Summary --}}
    <section class="card flex flex-col p-5 sm:p-6">
        <h2 class="text-[15px] font-semibold text-foreground">Performance</h2>
        <p class="mt-0.5 text-[13px] text-muted-foreground">For the {{ $periodLabel }}</p>

        <dl class="mt-5 flex-1 divide-y divide-border">
            <div class="flex items-center justify-between gap-4 pb-4">
                <dt class="flex items-center gap-2.5 text-[13px] text-muted-foreground">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-muted"><x-icon name="images" class="size-3.5" /></span>
                    Avg. views per gallery
                </dt>
                <dd class="font-display text-lg font-semibold tabular-nums text-foreground">{{ $avgViews }}</dd>
            </div>
            <div class="py-4">
                <div class="flex items-center justify-between gap-4">
                    <dt class="flex items-center gap-2.5 text-[13px] text-muted-foreground">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-muted"><x-icon name="percent" class="size-3.5" /></span>
                        Download-to-view ratio
                    </dt>
                    <dd class="font-display text-lg font-semibold tabular-nums text-foreground">{{ $dlRatio }}%</dd>
                </div>
                <div class="progress mt-3"><span style="width: {{ min(100, $dlRatio) }}%"></span></div>
            </div>
            <div class="flex items-center justify-between gap-4 pt-4">
                <dt class="flex items-center gap-2.5 text-[13px] text-muted-foreground">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-muted"><x-icon name="activity" class="size-3.5" /></span>
                    Client engagement
                </dt>
                <dd>
                    @if($totalDownloads > 0)
                        <x-ui.badge variant="success" dot>High activity</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Standard</x-ui.badge>
                    @endif
                </dd>
            </div>
        </dl>
    </section>
</div>

{{-- Top galleries --}}
<section class="card mt-6 overflow-hidden">
    <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 class="text-[15px] font-semibold text-foreground">Top galleries</h2>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Most viewed collections in the {{ $periodLabel }}</p>
        </div>
    </div>

    @if($topGalleries->isNotEmpty())
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Gallery</th>
                        <th class="w-[34%]">Views</th>
                        <th class="text-right">Downloads</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topGalleries as $g)
                        <tr>
                            <td class="tabular-nums text-muted-foreground">{{ $loop->iteration }}</td>
                            <td class="max-w-[16rem]">
                                <a href="{{ route('studio.galleries.show', $g->uuid) }}" class="block truncate font-medium text-foreground hover:underline hover:underline-offset-4">{{ $g->title }}</a>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="w-10 shrink-0 tabular-nums text-foreground">{{ number_format($g->views_count) }}</span>
                                    <div class="hidden h-1.5 flex-1 overflow-hidden rounded-full bg-muted sm:block">
                                        <span class="block h-full rounded-full bg-foreground/70" style="width: {{ max(2, ($g->views_count / $topMax) * 100) }}%"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right tabular-nums text-foreground">{{ number_format($g->downloads_count) }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-0.5">
                                    @if($user->username)
                                        <a href="{{ url('/p/' . $user->username . '/' . $g->slug) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Preview {{ $g->title }}" title="Preview"><x-icon name="eye" /></a>
                                    @endif
                                    <a href="{{ route('studio.galleries.show', $g->uuid) }}" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Manage {{ $g->title }}" title="Manage"><x-icon name="chevron-right" /></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="p-5 sm:p-6">
            <x-ui.empty-state icon="chart-column" title="No activity in this period"
                              description="Share your gallery links with clients. Views and downloads will show up here."  />
        </div>
    @endif
</section>
@endsection
