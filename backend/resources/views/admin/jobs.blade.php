@extends('layouts.admin', ['title' => 'Processing queue · Admin'])

@section('content')
@php
    $filterUrl = fn ($status = null) => route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => $status]));
@endphp
<div x-data="adminJobMonitor({
         initialMetrics: @js($metrics),
         initialMediaJobs: @js($initialMediaJobs),
         initialExportJobs: @js($initialExportJobs),
         initialGalleryBatches: @js($initialGalleryBatches ?? []),
         statusFilter: '{{ $statusFilter ?? '' }}',
         searchFilter: '{{ $searchFilter ?? '' }}'
     })"
     x-init="initPolling()"
     @beforeunload.window="destroy()">

    {{-- ================= Header ================= --}}
    <x-ui.page-header title="Processing queue" description="Live background jobs for photo and video optimization, ZIP packaging and cloud exports.">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                {{-- Live status pill --}}
                <span class="inline-flex h-6 items-center gap-1.5 rounded-full border px-2.5 font-medium"
                      :class="metrics.has_active_jobs ? 'border-brand/25 bg-brand/10 text-brand' : 'border-success/20 bg-success/10 text-success'">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-60"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-current"></span>
                    </span>
                    <span x-text="metrics.has_active_jobs ? 'Processing' : 'Live · idle'">Live</span>
                </span>
                {{-- Redis backlog --}}
                <span class="inline-flex h-6 items-center gap-1.5 rounded-full border border-border bg-card px-2.5 text-muted-foreground">
                    <x-icon name="database" class="size-3" />
                    Redis
                    <span class="font-mono text-foreground" x-text="(metrics.queue_backlog?.photos ?? 0) + ' photos · ' + (metrics.queue_backlog?.default ?? 0) + ' default'"></span>
                </span>
                <span class="inline-flex items-center gap-1 text-muted-foreground">
                    <x-icon name="refresh-cw" class="size-3" x-bind:class="polling && 'animate-spin'" />
                    <span x-text="polling ? 'Syncing…' : (lastSyncedAt ? 'Synced ' + lastSyncedAt : 'Auto-refresh every ' + (metrics.has_active_jobs ? '3s' : '20s'))"></span>
                </span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <button type="button" @click="retryFailed()"
                    :disabled="actionLoading || (!metrics.failed_total && !metrics.failed_today)"
                    class="btn btn-outline btn-sm text-destructive hover:text-destructive"
                    title="Re-queue all failed photo optimization jobs">
                <x-icon name="rotate-ccw" />
                Retry failed
                <span class="rounded bg-destructive/10 px-1 text-[11px] tabular-nums" x-text="metrics.failed_total || metrics.failed_today || 0"></span>
            </button>
            <button type="button" @click="retryAllQueued()"
                    :disabled="actionLoading || metrics.queued === 0"
                    class="btn btn-outline btn-sm"
                    title="Push all queued database records to the Redis worker queue">
                <x-icon name="zap" />
                Push queued
                <span class="rounded bg-muted px-1 text-[11px] tabular-nums text-muted-foreground" x-text="metrics.queued || 0"></span>
            </button>
            <button type="button" @click="restartWorkers()" :disabled="actionLoading"
                    class="btn btn-ghost btn-sm" title="Signal queue workers to restart gracefully">
                <x-icon name="server" />
                <span class="hidden sm:inline">Restart workers</span>
                <span class="sm:hidden">Restart</span>
            </button>
            <button type="button" @click="pollNow(true)" :disabled="polling" class="btn btn-primary btn-sm">
                <x-icon name="refresh-cw" x-bind:class="polling && 'animate-spin'" />
                <span x-text="polling ? 'Syncing…' : 'Sync now'">Sync now</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ================= Counters ================= --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {{-- Queued --}}
        <a href="{{ $filterUrl('queued') }}"
           class="card card-hover group block p-4 {{ $statusFilter === 'queued' ? 'ring-2 ring-foreground/80' : '' }}">
            <div class="flex items-center justify-between text-[13px] font-medium text-muted-foreground">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-border-strong"></span>Queued</span>
                <x-icon name="clock" class="size-3.5 opacity-60" />
            </div>
            <p class="stat-value mt-2" x-text="metrics.queued">{{ $metrics['queued'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">Waiting for a worker</p>
        </a>

        {{-- Processing --}}
        <a href="{{ $filterUrl('processing') }}"
           class="card card-hover group relative block overflow-hidden p-4 {{ $statusFilter === 'processing' ? 'ring-2 ring-brand' : '' }}">
            <div class="flex items-center justify-between text-[13px] font-medium text-muted-foreground">
                <span class="inline-flex items-center gap-2">
                    <span class="relative flex size-2">
                        <span x-show="metrics.processing > 0" class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-70"></span>
                        <span class="relative inline-flex size-2 rounded-full" :class="metrics.processing > 0 ? 'bg-brand' : 'bg-brand/40'"></span>
                    </span>
                    Processing
                </span>
                <x-icon name="loader-circle" class="size-3.5 opacity-60" x-bind:class="metrics.processing > 0 && 'animate-spin text-brand opacity-100'" />
            </div>
            <p class="stat-value mt-2" :class="metrics.processing > 0 && 'text-brand'" x-text="metrics.processing">{{ $metrics['processing'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">Actively optimizing</p>
            <div x-show="metrics.processing > 0" x-cloak class="absolute inset-x-0 bottom-0 h-0.5 overflow-hidden bg-brand/15">
                <div class="h-full w-1/3 animate-[jobs-indeterminate_1.4s_ease-in-out_infinite] bg-brand"></div>
            </div>
        </a>

        {{-- Failed --}}
        <a href="{{ $filterUrl('failed') }}"
           class="card card-hover group block p-4 {{ $statusFilter === 'failed' ? 'ring-2 ring-destructive' : '' }}">
            <div class="flex items-center justify-between text-[13px] font-medium text-muted-foreground">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-destructive"></span>Failed</span>
                <x-icon name="triangle-alert" class="size-3.5 opacity-60" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="stat-value" :class="(metrics.failed_total ?? metrics.failed_today) > 0 && 'text-destructive'" x-text="metrics.failed_total ?? metrics.failed_today">{{ $metrics['failed_total'] ?? $metrics['failed_today'] ?? 0 }}</p>
                <span x-show="metrics.failed_today !== undefined" class="text-xs tabular-nums text-muted-foreground" x-text="metrics.failed_today + ' today'"></span>
            </div>
            <p class="mt-0.5 text-xs text-muted-foreground">Needs attention</p>
        </a>

        {{-- Completed today --}}
        <a href="{{ $filterUrl('completed') }}"
           class="card card-hover group block p-4 {{ $statusFilter === 'completed' ? 'ring-2 ring-success' : '' }}">
            <div class="flex items-center justify-between text-[13px] font-medium text-muted-foreground">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-success"></span>Completed</span>
                <x-icon name="circle-check" class="size-3.5 opacity-60" />
            </div>
            <p class="stat-value mt-2" x-text="metrics.completed_today">{{ $metrics['completed_today'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">Since midnight</p>
        </a>

        {{-- Active exports --}}
        <button type="button" @click="activeTab = 'exports'; window.location.hash = 'exports'"
                class="card card-hover group col-span-2 block p-4 text-left sm:col-span-1">
            <div class="flex items-center justify-between text-[13px] font-medium text-muted-foreground">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-info"></span>Exports</span>
                <x-icon name="download" class="size-3.5 opacity-60" />
            </div>
            <p class="stat-value mt-2" :class="metrics.active_exports > 0 && 'text-info'" x-text="metrics.active_exports">{{ $metrics['active_exports'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">ZIPs & Google Photos</p>
        </button>
    </div>

    {{-- ================= Tabs ================= --}}
    <div class="tabs-line mt-8">
        <button type="button" @click="activeTab = 'jobs'; window.location.hash = 'jobs'"
                class="tab-line" :aria-selected="activeTab === 'jobs'">
            <x-icon name="images" class="size-4" />
            Media jobs
            <span class="rounded-md bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{{ $mediaJobs->total() }}</span>
        </button>
        <button type="button" @click="activeTab = 'exports'; window.location.hash = 'exports'"
                class="tab-line" :aria-selected="activeTab === 'exports'">
            <x-icon name="download" class="size-4" />
            Exports
            <span class="rounded-md bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{{ $exportJobs->total() }}</span>
        </button>
    </div>

    {{-- ================= TAB 1: Media jobs ================= --}}
    <div x-show="activeTab === 'jobs'" class="mt-5 space-y-4">
        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="tabs">
                <button type="button" @click="viewMode = 'batches'" class="tab" :aria-selected="viewMode === 'batches'">
                    <x-icon name="layers" />
                    By gallery
                    <span x-show="galleryBatches.length > 0" class="rounded bg-brand/15 px-1 text-[11px] font-semibold tabular-nums text-brand" x-text="galleryBatches.length"></span>
                </button>
                <button type="button" @click="viewMode = 'stream'" class="tab" :aria-selected="viewMode === 'stream'">
                    <x-icon name="list" />
                    All jobs
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <form method="GET" action="{{ route('admin.jobs.index') }}" x-show="viewMode === 'stream'" class="sm:w-72">
                    @if($statusFilter)<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                    <div class="input-group">
                        <x-icon name="search" />
                        <input type="text" name="search" value="{{ $searchFilter }}" placeholder="Filename, photo UUID, studio…" class="input input-sm pr-8">
                        @if($searchFilter)
                            <a href="{{ route('admin.jobs.index', array_filter(['status' => $statusFilter])) }}" class="absolute right-2 flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Clear search">
                                <x-icon name="x" class="size-3.5" />
                            </a>
                        @endif
                    </div>
                </form>

                <nav class="tabs max-w-full overflow-x-auto scrollbar-none" aria-label="Filter by status">
                    @foreach(['' => 'All', 'queued' => 'Queued', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed'] as $key => $label)
                        @php($isActive = ($key === '' && empty($statusFilter)) || $statusFilter === $key)
                        <a href="{{ $filterUrl($key ?: null) }}" class="tab whitespace-nowrap {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </div>

        @if($searchFilter)
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <x-icon name="funnel" class="size-3.5" /> Showing jobs matching <span class="font-medium text-foreground">“{{ $searchFilter }}”</span>
            </p>
        @endif

        {{-- VIEW 1: By gallery --}}
        <div x-show="viewMode === 'batches'" class="space-y-3">
            <template x-for="batch in galleryBatches" :key="batch.gallery_id">
                <div class="card p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                                  :class="batch.failed_photos > 0 ? 'bg-destructive/10 text-destructive' : (batch.processing_photos > 0 ? 'bg-brand/10 text-brand' : 'bg-muted text-muted-foreground')">
                                <x-icon name="images" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <h4 class="truncate text-sm font-semibold text-foreground" x-text="batch.gallery_title"></h4>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    <span x-text="batch.studio_name"></span>
                                    · <span class="tabular-nums" x-text="batch.total_photos + ' photos'"></span>
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" @click="retryGallery(batch.gallery_id)" :disabled="actionLoading" class="btn btn-outline btn-xs">
                                <x-icon name="rotate-ccw" /> Retry pending
                            </button>
                            <a :href="'{{ route('admin.jobs.index') }}?search=' + encodeURIComponent(batch.gallery_title)" class="btn btn-ghost btn-xs">
                                Inspect <x-icon name="arrow-right" />
                            </a>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs">
                            <span class="text-muted-foreground">
                                <span class="font-display text-base font-semibold tabular-nums text-foreground" x-text="batch.progress_percentage + '%'"></span>
                                processed · <span class="tabular-nums" x-text="batch.completed_photos + '/' + batch.total_photos"></span>
                            </span>
                            <div class="flex items-center gap-3 tabular-nums">
                                <span x-show="batch.queued_photos > 0" class="inline-flex items-center gap-1 text-muted-foreground"><span class="size-1.5 rounded-full bg-border-strong"></span><span x-text="batch.queued_photos + ' queued'"></span></span>
                                <span x-show="batch.processing_photos > 0" class="inline-flex items-center gap-1 text-brand"><span class="size-1.5 animate-pulse rounded-full bg-brand"></span><span x-text="batch.processing_photos + ' processing'"></span></span>
                                <span x-show="batch.failed_photos > 0" class="inline-flex items-center gap-1 text-destructive"><span class="size-1.5 rounded-full bg-destructive"></span><span x-text="batch.failed_photos + ' failed'"></span></span>
                            </div>
                        </div>
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full bg-success transition-[width] duration-500" :style="'width: ' + batch.progress_percentage + '%'"></div>
                            <div x-show="batch.processing_photos > 0" class="h-full animate-pulse bg-brand transition-[width] duration-500" :style="'width: ' + ((batch.processing_photos / Math.max(1, batch.total_photos)) * 100) + '%'"></div>
                            <div x-show="batch.failed_photos > 0" class="h-full bg-destructive transition-[width] duration-500" :style="'width: ' + ((batch.failed_photos / Math.max(1, batch.total_photos)) * 100) + '%'"></div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="galleryBatches.length === 0">
                <x-ui.empty-state icon="circle-check" title="All galleries are up to date" description="No uploads are waiting or being processed right now. New batches appear here the moment photographers upload." />
            </div>
        </div>

        {{-- VIEW 2: All jobs --}}
        <div x-show="viewMode === 'stream'" class="card overflow-hidden">
            {{-- Mobile list --}}
            <ul class="divide-y divide-border md:hidden">
                <template x-for="job in mediaJobs" :key="'m-' + job.id">
                    <li class="px-4 py-3" x-data="{ expanded: false }">
                        <div class="flex items-start justify-between gap-3">
                            <button type="button" class="min-w-0 text-left" @click="openStageModal(job)">
                                <div class="truncate text-[13px] font-medium text-foreground" x-text="job.original_filename"></div>
                                <div class="truncate text-xs text-muted-foreground" x-text="job.gallery_title + ' · ' + job.studio_name"></div>
                            </button>
                            <span class="badge shrink-0" :class="pillClass(job.status)">
                                <span class="relative flex size-1.5">
                                    <span x-show="job.status === 'processing'" class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-70"></span>
                                    <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
                                </span>
                                <span class="capitalize" x-text="job.status"></span>
                            </span>
                        </div>
                        <div class="mt-2.5 flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full transition-[width] duration-500" :class="barClass(job.status)"
                                     :style="'width: ' + (job.percentage ?? (job.status === 'completed' ? 100 : 0)) + '%'"></div>
                            </div>
                            <span class="w-9 shrink-0 text-right font-mono text-[11px] tabular-nums text-muted-foreground" x-text="(job.percentage ?? (job.status === 'completed' ? 100 : 0)) + '%'"></span>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                            <span class="truncate" x-text="(job.stage_label || job.progress || '') + ' · ' + job.attempts + '/' + job.max_attempts + ' attempts'"></span>
                            <span class="shrink-0 font-mono" x-text="job.duration_ms !== null && job.duration_ms !== undefined ? formatDuration(job.duration_ms) : '—'"></span>
                        </div>
                        <div x-show="['failed', 'queued'].includes(job.status)" class="mt-2.5 flex items-center gap-2">
                            <button type="button" x-show="job.status === 'failed'" @click="expanded = !expanded" class="btn btn-ghost btn-xs">
                                Error <x-icon name="chevron-down" class="transition-transform" x-bind:class="expanded && 'rotate-180'" />
                            </button>
                            <button type="button" @click="retryJob(job.id)" :disabled="actionLoading" class="btn btn-outline btn-xs ml-auto">
                                <x-icon name="rotate-ccw" /> Retry
                            </button>
                        </div>
                        <pre x-show="job.status === 'failed' && expanded" x-cloak
                             class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-destructive/20 bg-destructive/5 p-2.5 font-mono text-[11px] leading-relaxed text-destructive"
                             x-text="job.error_message || 'Job exited with an unhandled exception.'"></pre>
                    </li>
                </template>
                <li x-show="mediaJobs.length === 0" class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-foreground">No jobs match this filter</p>
                    <p class="mt-1 text-[13px] text-muted-foreground">Try another status or clear the search.</p>
                </li>
            </ul>

            <div class="table-wrap relative hidden md:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Gallery</th>
                            <th>Status</th>
                            <th class="min-w-[180px]">Progress</th>
                            <th class="text-center">Attempts</th>
                            <th>Duration</th>
                            <th>Timeline</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <template x-for="job in mediaJobs" :key="job.id">
                        <tbody x-data="{ expanded: false }" class="border-b border-border last:border-b-0">
                            <tr class="[&>td]:border-b-0">
                                <td>
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <template x-if="job.media_type === 'video'"><x-icon name="video" class="size-3.5" /></template>
                                            <template x-if="job.media_type !== 'video'"><x-icon name="image" class="size-3.5" /></template>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="max-w-[170px] truncate text-[13px] font-medium text-foreground" :title="job.original_filename" x-text="job.original_filename"></div>
                                            <div class="max-w-[170px] truncate font-mono text-[11px] text-muted-foreground" :title="job.photo_uuid" x-text="job.photo_uuid || 'N/A'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="max-w-[160px] truncate text-[13px] text-foreground" :title="job.gallery_title" x-text="job.gallery_title"></div>
                                    <div class="max-w-[160px] truncate text-xs text-muted-foreground" x-text="job.studio_name"></div>
                                </td>
                                <td>
                                    <span class="badge" :class="pillClass(job.status)">
                                        <span class="relative flex size-1.5">
                                            <span x-show="job.status === 'processing'" class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-70"></span>
                                            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
                                        </span>
                                        <span class="capitalize" x-text="job.status"></span>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" @click="openStageModal(job)"
                                            class="group -mx-2 -my-1.5 block w-[calc(100%+1rem)] rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-muted"
                                            :title="'Job #' + job.id + ' · view stage pipeline'">
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <span class="inline-flex min-w-0 items-center gap-1 font-medium text-foreground">
                                                <span class="truncate" x-text="job.stage_label || job.progress || (job.status === 'completed' ? 'Completed' : 'Queued')"></span>
                                                <x-icon name="chevron-right" class="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                            </span>
                                            <span class="shrink-0 font-mono tabular-nums text-muted-foreground" x-text="(job.percentage ?? (job.status === 'completed' ? 100 : 0)) + '%'"></span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                            <div class="h-full rounded-full transition-[width] duration-500" :class="barClass(job.status)"
                                                 :style="'width: ' + (job.percentage ?? (job.status === 'completed' ? 100 : 0)) + '%'"></div>
                                        </div>
                                    </button>
                                </td>
                                <td class="text-center font-mono text-xs tabular-nums" :class="job.attempts >= job.max_attempts ? 'text-destructive' : 'text-muted-foreground'" x-text="job.attempts + '/' + job.max_attempts"></td>
                                <td class="whitespace-nowrap font-mono text-xs text-muted-foreground">
                                    <span x-show="job.duration_ms !== null && job.duration_ms !== undefined" x-text="formatDuration(job.duration_ms)"></span>
                                    <span x-show="job.duration_ms === null && job.status === 'processing' && job.started_at" class="text-brand">running</span>
                                    <span x-show="job.duration_ms === null && !(job.status === 'processing' && job.started_at)">—</span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted-foreground">
                                    <div x-show="job.started_at" class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-border-strong"></span><span class="font-mono tabular-nums" x-text="formatTime(job.started_at)"></span></div>
                                    <div x-show="job.completed_at" class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-success"></span><span class="font-mono tabular-nums" x-text="formatTime(job.completed_at)"></span></div>
                                    <div x-show="job.failed_at" class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-destructive"></span><span class="font-mono tabular-nums" x-text="formatTime(job.failed_at)"></span></div>
                                    <span x-show="!job.started_at && !job.completed_at && !job.failed_at">—</span>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" x-show="job.status === 'failed'" @click="expanded = !expanded"
                                                class="btn btn-ghost btn-xs" :aria-expanded="expanded">
                                            Error <x-icon name="chevron-down" class="transition-transform" x-bind:class="expanded && 'rotate-180'" />
                                        </button>
                                        <button type="button" x-show="['failed', 'queued'].includes(job.status)" @click="retryJob(job.id)" :disabled="actionLoading"
                                                class="btn btn-outline btn-xs">
                                            <x-icon name="rotate-ccw" /> Retry
                                        </button>
                                        <span x-show="job.status === 'completed'" class="inline-flex size-7 items-center justify-center text-success" title="Done"><x-icon name="circle-check" class="size-4" /><span class="sr-only">Done</span></span>
                                        <span x-show="job.status === 'processing'" class="inline-flex size-7 items-center justify-center text-brand" title="Active"><x-icon name="loader-circle" class="size-4 animate-spin" /><span class="sr-only">Active</span></span>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="job.status === 'failed' && expanded" x-cloak class="hover:bg-transparent">
                                <td colspan="8" class="pt-0">
                                    <div class="rounded-lg border border-destructive/20 bg-destructive/5 p-3">
                                        <div class="mb-1.5 flex items-center justify-between gap-2">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-destructive"><x-icon name="circle-alert" class="size-3.5" /> Failure diagnostic</span>
                                            <button type="button" class="btn btn-ghost btn-xs" @click="openStageModal(job)">Open pipeline</button>
                                        </div>
                                        <pre class="max-h-48 overflow-auto whitespace-pre-wrap break-words font-mono text-[11px] leading-relaxed text-destructive" x-text="job.error_message || 'Job exited with an unhandled exception.'"></pre>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                    <tbody x-show="mediaJobs.length === 0">
                        <tr class="hover:bg-transparent">
                            <td colspan="8" class="py-14 text-center">
                                <div class="mx-auto flex max-w-xs flex-col items-center">
                                    <span class="mb-3 flex size-10 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-xs"><x-icon name="inbox" class="size-4" /></span>
                                    <p class="text-sm font-medium text-foreground">No jobs match this filter</p>
                                    <p class="mt-1 text-[13px] text-muted-foreground">Try another status or clear the search.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if($mediaJobs->hasPages())
                <div class="border-t border-border px-4 py-3 sm:px-5">
                    {{ $mediaJobs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ================= TAB 2: Exports ================= --}}
    <div x-show="activeTab === 'exports'" x-cloak class="mt-5">
        <div class="card overflow-hidden">
            <div class="table-wrap relative">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Gallery</th>
                            <th>Status</th>
                            <th class="min-w-[180px]">Progress</th>
                            <th>Notify</th>
                            <th>ETA / duration</th>
                            <th>Timeline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="exportItem in exportJobs" :key="exportItem.type + '-' + exportItem.id">
                            <tr>
                                <td>
                                    <span class="badge" :class="exportItem.type === 'zip' ? 'badge-neutral' : 'badge-info'">
                                        <template x-if="exportItem.type === 'zip'"><x-icon name="folder" /></template>
                                        <template x-if="exportItem.type !== 'zip'"><x-icon name="cloud-upload" /></template>
                                        <span x-text="exportItem.type === 'zip' ? 'ZIP archive' : 'Google Photos'"></span>
                                    </span>
                                    <div class="mt-1 font-mono text-[11px] text-muted-foreground" x-text="'#' + exportItem.id"></div>
                                </td>
                                <td>
                                    <div class="max-w-[200px] truncate text-[13px] text-foreground" :title="exportItem.gallery_title" x-text="exportItem.gallery_title"></div>
                                    <div class="max-w-[200px] truncate text-xs text-muted-foreground" x-text="exportItem.studio_name"></div>
                                </td>
                                <td>
                                    <span class="badge"
                                          :class="['ready', 'completed'].includes(exportItem.status) ? 'badge-success' : (exportItem.status === 'failed' ? 'badge-danger' : (['processing', 'pending'].includes(exportItem.status) ? 'badge-brand' : 'badge-neutral'))">
                                        <span class="relative flex size-1.5">
                                            <span x-show="['processing', 'pending'].includes(exportItem.status)" class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-70"></span>
                                            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
                                        </span>
                                        <span class="capitalize" x-text="exportItem.status.replace('_', ' ')"></span>
                                    </span>
                                </td>
                                <td>
                                    <template x-if="exportItem.indeterminate">
                                        <div class="max-w-[180px]">
                                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><x-icon name="loader-circle" class="size-3.5 animate-spin text-brand" /> Syncing…</div>
                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-brand/15">
                                                <div class="h-full w-1/3 animate-[jobs-indeterminate_1.4s_ease-in-out_infinite] rounded-full bg-brand"></div>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!exportItem.indeterminate">
                                        <div class="max-w-[180px]">
                                            <div class="flex justify-between text-xs">
                                                <span class="font-medium tabular-nums text-foreground" x-text="exportItem.percentage + '%'"></span>
                                                <span class="font-mono text-[11px] tabular-nums text-muted-foreground" x-text="(exportItem.processed_photos + exportItem.failed_photos) + '/' + exportItem.total_photos"></span>
                                            </div>
                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div class="h-full rounded-full transition-[width] duration-500"
                                                     :class="['ready', 'completed'].includes(exportItem.status) ? 'bg-success' : (exportItem.status === 'failed' ? 'bg-destructive' : 'bg-brand')"
                                                     :style="'width: ' + exportItem.percentage + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <td>
                                    <template x-if="exportItem.email">
                                        <div class="flex max-w-[200px] items-center gap-1.5 text-xs text-foreground">
                                            <x-icon name="mail" class="size-3.5 shrink-0 text-muted-foreground" />
                                            <span class="truncate font-mono text-[11px]" x-text="exportItem.email"></span>
                                        </div>
                                    </template>
                                    <template x-if="!exportItem.email">
                                        <span class="text-xs text-subtle">—</span>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span x-text="formatEta(exportItem)" class="font-mono text-xs"
                                          :class="exportItem.status === 'processing' && exportItem.remaining_seconds !== null ? 'font-medium text-brand' : 'text-muted-foreground'"></span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted-foreground">
                                    <div class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-border-strong"></span><span x-text="formatDate(exportItem.created_at)"></span></div>
                                    <div x-show="exportItem.completed_at" class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-success"></span><span x-text="formatDate(exportItem.completed_at)"></span></div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="exportJobs.length === 0" class="hover:bg-transparent">
                            <td colspan="7" class="py-14 text-center">
                                <div class="mx-auto flex max-w-xs flex-col items-center">
                                    <span class="mb-3 flex size-10 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-xs"><x-icon name="download" class="size-4" /></span>
                                    <p class="text-sm font-medium text-foreground">No exports yet</p>
                                    <p class="mt-1 text-[13px] text-muted-foreground">ZIP downloads and Google Photos syncs will be tracked here.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if($exportJobs->hasPages())
                <div class="border-t border-border px-4 py-3 sm:px-5">
                    {{ $exportJobs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ================= Stage pipeline modal ================= --}}
    <div x-show="stageModalOpen"
         x-transition.opacity
         class="fixed inset-0 z-[80] flex items-end justify-center overflow-y-auto bg-black/40 p-4 backdrop-blur-[2px] sm:items-center sm:p-6"
         style="display: none;"
         role="dialog" aria-modal="true" aria-labelledby="stage-modal-title"
         @click.self="stageModalOpen = false"
         @keydown.escape.window="stageModalOpen = false">

        <div class="my-4 flex max-h-[90dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-border bg-popover text-popover-foreground shadow-xl">
            {{-- Header --}}
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-5 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <template x-if="activeStageJob?.media_type === 'video'"><x-icon name="video" class="size-4" /></template>
                        <template x-if="activeStageJob?.media_type !== 'video'"><x-icon name="image" class="size-4" /></template>
                    </span>
                    <div class="min-w-0">
                        <h3 id="stage-modal-title" class="truncate text-[15px] font-semibold text-foreground" x-text="activeStageJob?.original_filename || 'Processing pipeline'"></h3>
                        <p class="text-xs text-muted-foreground">Processing pipeline · live</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="badge" :class="pillClass(activeStageJob?.status)">
                        <span class="relative flex size-1.5">
                            <span x-show="activeStageJob?.status === 'processing'" class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-70"></span>
                            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
                        </span>
                        <span class="capitalize" x-text="activeStageJob?.status"></span>
                    </span>
                    <button type="button" @click="stageModalOpen = false" class="btn btn-ghost btn-icon btn-sm" aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="min-h-0 space-y-5 overflow-y-auto overscroll-contain p-5">
                {{-- Overall progress --}}
                <div class="surface-muted p-4">
                    <div class="flex items-end justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-xs text-muted-foreground">Current stage</div>
                            <div class="mt-0.5 flex items-center gap-2 truncate text-[15px] font-semibold text-foreground">
                                <span class="truncate" x-text="activeStageJob?.stage_label || activeStageJob?.progress"></span>
                                <span x-show="activeStageJob?.status === 'processing'" class="relative flex size-2 shrink-0">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-70"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-brand"></span>
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 font-display text-2xl font-semibold tabular-nums"
                             :class="{
                                 'text-success': activeStageJob?.status === 'completed',
                                 'text-brand': activeStageJob?.status === 'processing',
                                 'text-destructive': activeStageJob?.status === 'failed',
                                 'text-muted-foreground': activeStageJob?.status === 'queued'
                             }"
                             x-text="(activeStageJob?.percentage ?? 0) + '%'"></div>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full transition-[width] duration-500" :class="barClass(activeStageJob?.status)"
                             :style="'width: ' + (activeStageJob?.percentage ?? (activeStageJob?.status === 'completed' ? 100 : 0)) + '%'"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                        <span x-text="activeStageJob?.status === 'completed' ? 'All optimization tasks completed' : (activeStageJob?.status === 'processing' ? 'A worker is processing this file right now' : (activeStageJob?.status === 'failed' ? 'Job halted due to an exception' : 'Waiting in the Redis queue for a worker'))"></span>
                        <span class="shrink-0 font-mono" x-show="activeStageJob?.duration_ms" x-text="'Total ' + formatDuration(activeStageJob?.duration_ms)"></span>
                    </div>
                </div>

                {{-- Context --}}
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-4">
                    <div class="bg-popover p-3">
                        <dt class="text-[11px] text-muted-foreground">File</dt>
                        <dd class="mt-0.5 truncate text-xs font-medium text-foreground" :title="activeStageJob?.original_filename" x-text="activeStageJob?.original_filename"></dd>
                    </div>
                    <div class="bg-popover p-3">
                        <dt class="text-[11px] text-muted-foreground">Gallery</dt>
                        <dd class="mt-0.5 truncate text-xs font-medium text-foreground" :title="activeStageJob?.gallery_title" x-text="activeStageJob?.gallery_title"></dd>
                        <dd class="truncate text-[11px] text-muted-foreground" x-text="activeStageJob?.studio_name"></dd>
                    </div>
                    <div class="bg-popover p-3">
                        <dt class="text-[11px] text-muted-foreground">Queue · attempts</dt>
                        <dd class="mt-0.5 font-mono text-xs text-foreground" x-text="(activeStageJob?.queue || 'photos') + ' · ' + (activeStageJob?.attempts || 1) + '/' + (activeStageJob?.max_attempts || 3)"></dd>
                    </div>
                    <div class="bg-popover p-3">
                        <dt class="text-[11px] text-muted-foreground">Job · photo</dt>
                        <dd class="mt-0.5 truncate font-mono text-xs text-foreground" :title="activeStageJob?.photo_uuid" x-text="'#' + activeStageJob?.id + ' · ' + (activeStageJob?.photo_uuid ? activeStageJob?.photo_uuid.substring(0, 8) + '…' : 'N/A')"></dd>
                    </div>
                </dl>

                {{-- Failure --}}
                <template x-if="activeStageJob?.status === 'failed'">
                    <div class="rounded-xl border border-destructive/20 bg-destructive/5 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-destructive"><x-icon name="circle-alert" class="size-4" /> Failure diagnostic</span>
                            <button type="button" @click="retryJob(activeStageJob.id); stageModalOpen = false;" class="btn btn-danger btn-xs">
                                <x-icon name="rotate-ccw" /> Retry now
                            </button>
                        </div>
                        <pre class="mt-3 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-destructive/10 bg-background/60 p-3 font-mono text-[11px] leading-relaxed text-destructive"
                             x-text="activeStageJob?.error_message || 'Job exited with an unhandled exception.'"></pre>
                    </div>
                </template>

                {{-- Pipeline --}}
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-[13px] font-semibold text-foreground">Pipeline</h4>
                        <span class="font-mono text-[11px] text-muted-foreground" x-text="(activeStageJob?.stages?.filter(s => s.status === 'completed').length || 0) + ' of ' + (activeStageJob?.stages?.length || 0) + ' stages complete'"></span>
                    </div>

                    <ol class="relative space-y-2 pl-9 before:absolute before:bottom-4 before:left-[13px] before:top-4 before:w-px before:bg-border">
                        <template x-for="(stage, idx) in activeStageJob?.stages" :key="stage.id">
                            <li class="relative">
                                <span class="absolute -left-9 top-2.5 flex size-[26px] items-center justify-center rounded-full font-mono text-[11px] font-semibold ring-4 ring-popover"
                                      :class="{
                                          'bg-success text-white': stage.status === 'completed',
                                          'bg-brand-strong text-brand-foreground': stage.status === 'current',
                                          'bg-destructive text-white': stage.status === 'failed',
                                          'border border-border bg-muted text-muted-foreground': stage.status === 'pending' || stage.status === 'queued'
                                      }">
                                    <template x-if="stage.status === 'completed'"><x-icon name="check" class="size-3.5" /></template>
                                    <template x-if="stage.status === 'current'"><x-icon name="loader-circle" class="size-3.5 animate-spin" /></template>
                                    <template x-if="stage.status === 'failed'"><x-icon name="x" class="size-3.5" /></template>
                                    <template x-if="stage.status === 'pending' || stage.status === 'queued'"><span x-text="stage.step_number"></span></template>
                                </span>
                                <div class="rounded-lg border px-3.5 py-2.5 transition-colors"
                                     :class="{
                                         'border-border bg-popover': stage.status === 'completed',
                                         'border-brand/30 bg-brand/5': stage.status === 'current',
                                         'border-destructive/30 bg-destructive/5': stage.status === 'failed',
                                         'border-dashed border-border bg-transparent opacity-70': stage.status === 'pending' || stage.status === 'queued'
                                     }">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[13px] font-medium text-foreground" x-text="stage.name"></span>
                                            <span class="rounded bg-muted px-1 font-mono text-[10px] text-muted-foreground" x-text="'~' + stage.target_percentage + '%'"></span>
                                        </div>
                                        <span x-show="stage.status === 'completed'" class="inline-flex items-center gap-1 text-[11px] font-medium text-success"><x-icon name="check" class="size-3" /> Done</span>
                                        <span x-show="stage.status === 'current'" class="inline-flex items-center gap-1.5 text-[11px] font-medium text-brand"><span class="size-1.5 animate-pulse rounded-full bg-brand"></span> Running</span>
                                        <span x-show="stage.status === 'failed'" class="inline-flex items-center gap-1 text-[11px] font-medium text-destructive"><x-icon name="x" class="size-3" /> Failed here</span>
                                        <span x-show="stage.status === 'pending' || stage.status === 'queued'" class="text-[11px] text-muted-foreground">Pending</span>
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground" x-text="stage.description"></p>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-border bg-muted/40 px-5 py-3">
                <button type="button" @click="copyJobDiagnostics(activeStageJob)" class="btn btn-ghost btn-sm">
                    <x-icon name="check" x-show="copiedDiagnostics" class="text-success" />
                    <x-icon name="copy" x-show="!copiedDiagnostics" />
                    <span x-text="copiedDiagnostics ? 'Copied' : 'Copy diagnostics'"></span>
                </button>
                <div class="flex items-center gap-2">
                    <button x-show="['failed', 'queued'].includes(activeStageJob?.status)"
                            @click="retryJob(activeStageJob.id); stageModalOpen = false;"
                            :disabled="actionLoading" type="button" class="btn btn-primary btn-sm">
                        <x-icon name="rotate-ccw" /> Retry job
                    </button>
                    <button type="button" @click="stageModalOpen = false" class="btn btn-outline btn-sm">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= Error detail modal ================= --}}
    <div x-show="errorModalOpen"
         x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4 backdrop-blur-[2px]"
         style="display: none;"
         role="dialog" aria-modal="true"
         @click.self="errorModalOpen = false">
        <div class="w-full max-w-lg max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain space-y-4 rounded-2xl border border-border bg-popover p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-[15px] font-semibold text-foreground">
                    <x-icon name="circle-alert" class="size-4 text-destructive" />
                    Processing failure
                </h3>
                <button type="button" @click="errorModalOpen = false" class="btn btn-ghost btn-icon btn-sm" aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div>
                <div class="text-xs text-muted-foreground">File</div>
                <div class="mt-0.5 font-mono text-xs text-foreground" x-text="activeError?.filename"></div>
            </div>
            <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-destructive/20 bg-destructive/5 p-3 font-mono text-[11px] leading-relaxed text-destructive"
                 x-text="activeError?.message"></pre>
            <div class="flex justify-end">
                <button type="button" @click="errorModalOpen = false" class="btn btn-outline btn-sm">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes jobs-indeterminate {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(300%); }
    }
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminJobMonitor', (config) => ({
        activeTab: (window.location.hash === '#exports') ? 'exports' : 'jobs',
        viewMode: (config.initialGalleryBatches && config.initialGalleryBatches.length > 0) ? 'batches' : 'stream',
        metrics: config.initialMetrics || {
            queued: 0,
            processing: 0,
            failed_today: 0,
            failed_total: 0,
            completed_today: 0,
            active_exports: 0,
            has_active_jobs: false,
            queue_backlog: { photos: 0, default: 0 }
        },
        mediaJobs: config.initialMediaJobs || [],
        exportJobs: config.initialExportJobs || [],
        galleryBatches: config.initialGalleryBatches || [],
        statusFilter: config.statusFilter || '',
        searchFilter: config.searchFilter || '',
        polling: false,
        pollTimer: null,
        errorModalOpen: false,
        activeError: null,
        stageModalOpen: false,
        activeStageJob: null,
        copiedDiagnostics: false,
        actionLoading: false,
        lastSyncedAt: null,

        csrfToken() {
            return '{{ csrf_token() }}';
        },

        showToast(message, type = 'success') {
            if (typeof window.toast === 'function') {
                window.toast(message, type === 'success' ? 'success' : 'error', 6000);
            }
        },

        pillClass(status) {
            return {
                completed: 'badge-success',
                processing: 'badge-brand',
                failed: 'badge-danger',
                queued: 'badge-neutral',
            }[status] || 'badge-neutral';
        },

        barClass(status) {
            return {
                completed: 'bg-success',
                processing: 'bg-brand animate-pulse',
                failed: 'bg-destructive',
                queued: 'bg-border-strong',
            }[status] || 'bg-border-strong';
        },

        initPolling() {
            if (this.metrics.has_active_jobs) {
                this.pollNow(false);
            } else {
                this.scheduleNextPoll();
            }
        },

        scheduleNextPoll() {
            this.clearPollTimer();
            const interval = this.metrics.has_active_jobs ? 3000 : 20000;
            this.pollTimer = setTimeout(() => {
                this.pollNow(false);
            }, interval);
        },

        clearPollTimer() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async pollNow(manual = false) {
            if (this.polling) return;
            this.polling = true;

            try {
                const params = new URLSearchParams();
                if (this.statusFilter) params.set('status', this.statusFilter);
                if (this.searchFilter) params.set('search', this.searchFilter);

                const res = await fetch(`/admin/queue/status?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if ([401, 403, 419].includes(res.status)) {
                    this.clearPollTimer();
                    return;
                }

                if (!res.ok) {
                    throw new Error('Status poll returned HTTP ' + res.status);
                }

                const data = await res.json();
                if (data.metrics) {
                    this.metrics = data.metrics;
                }
                if (data.media_jobs) {
                    this.mediaJobs = data.media_jobs;
                    if (this.stageModalOpen && this.activeStageJob) {
                        const updated = this.mediaJobs.find(j => j.id === this.activeStageJob.id);
                        if (updated) {
                            this.activeStageJob = updated;
                        }
                    }
                }
                if (data.exports) {
                    this.exportJobs = data.exports;
                }
                if (data.gallery_batches) {
                    this.galleryBatches = data.gallery_batches;
                }
                this.lastSyncedAt = new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            } catch (err) {
                console.warn('Queue poll error:', err);
            } finally {
                this.polling = false;
                this.scheduleNextPoll();
            }
        },

        async retryJob(id) {
            if (this.actionLoading) return;
            this.actionLoading = true;

            try {
                const res = await fetch(`/admin/queue/retry/${id}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Job re-queued successfully.', 'success');
                    await this.pollNow(true);
                } else {
                    this.showToast(data.error || 'Failed to retry job.', 'error');
                }
            } catch (e) {
                this.showToast('Network error while retrying job.', 'error');
            } finally {
                this.actionLoading = false;
            }
        },

        async retryFailed() {
            if (this.actionLoading) return;
            if (!confirm('Re-queue all failed photo optimization jobs?')) return;
            this.actionLoading = true;

            try {
                const res = await fetch('/admin/queue/retry-failed', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Failed jobs re-queued.', 'success');
                    await this.pollNow(true);
                } else {
                    this.showToast(data.error || 'Failed to retry failed jobs.', 'error');
                }
            } catch (e) {
                this.showToast('Network error while retrying failed jobs.', 'error');
            } finally {
                this.actionLoading = false;
            }
        },

        async retryAllQueued() {
            if (this.actionLoading) return;
            this.actionLoading = true;

            try {
                const res = await fetch('/admin/queue/retry-all-queued', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Queued jobs refreshed.', 'success');
                    await this.pollNow(true);
                } else {
                    this.showToast(data.error || 'Failed to refresh queued jobs.', 'error');
                }
            } catch (e) {
                this.showToast('Network error while refreshing queued jobs.', 'error');
            } finally {
                this.actionLoading = false;
            }
        },

        async retryGallery(galleryId) {
            if (this.actionLoading) return;
            this.actionLoading = true;

            try {
                const res = await fetch(`/admin/queue/retry-gallery/${galleryId}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Gallery jobs re-queued.', 'success');
                    await this.pollNow(true);
                } else {
                    this.showToast(data.error || 'Failed to re-queue gallery jobs.', 'error');
                }
            } catch (e) {
                this.showToast('Network error while re-queuing gallery.', 'error');
            } finally {
                this.actionLoading = false;
            }
        },

        async restartWorkers() {
            if (this.actionLoading) return;
            if (!confirm('Signal queue workers to restart gracefully?')) return;
            this.actionLoading = true;

            try {
                const res = await fetch('/admin/queue/restart-workers', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Worker restart signal broadcasted.', 'success');
                    await this.pollNow(true);
                } else {
                    this.showToast(data.error || 'Failed to broadcast restart signal.', 'error');
                }
            } catch (e) {
                this.showToast('Network error while restarting workers.', 'error');
            } finally {
                this.actionLoading = false;
            }
        },

        formatTime(val) {
            if (!val) return '—';
            try {
                const d = new Date(val);
                if (isNaN(d.getTime())) return val;
                return d.toTimeString().split(' ')[0];
            } catch (e) {
                return val;
            }
        },

        formatDate(val) {
            if (!val) return '—';
            try {
                const d = new Date(val);
                if (isNaN(d.getTime())) return val;
                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ', ' +
                       d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false });
            } catch (e) {
                return val;
            }
        },

        formatDuration(ms) {
            if (ms === null || ms === undefined) return '—';
            const absMs = Math.abs(ms);
            return absMs < 1000 ? absMs + 'ms' : (absMs / 1000).toFixed(2) + 's';
        },

        formatEta(item) {
            if (item.status === 'processing' && item.remaining_seconds !== null && item.remaining_seconds !== undefined) {
                const s = item.remaining_seconds;
                return '~' + (s > 60 ? Math.ceil(s / 60) + 'm' : s + 's');
            }
            if (item.elapsed_seconds !== null && item.elapsed_seconds !== undefined) {
                return '~' + item.elapsed_seconds + 's';
            }
            return '—';
        },

        openStageModal(job) {
            this.activeStageJob = job;
            this.copiedDiagnostics = false;
            this.stageModalOpen = true;
        },

        copyJobDiagnostics(job) {
            if (!job) return;
            const dataToCopy = {
                job_id: job.id,
                job_uuid: job.job_uuid,
                queue: job.queue,
                status: job.status,
                media_type: job.media_type,
                progress_stage: job.stage_label || job.progress,
                percentage: job.percentage,
                attempts: `${job.attempts}/${job.max_attempts}`,
                duration_ms: job.duration_ms,
                started_at: job.started_at,
                completed_at: job.completed_at,
                failed_at: job.failed_at,
                error_message: job.error_message,
                photo_uuid: job.photo_uuid,
                filename: job.original_filename,
                gallery: job.gallery_title,
                studio: job.studio_name,
                stages: job.stages
            };
            navigator.clipboard.writeText(JSON.stringify(dataToCopy, null, 2)).then(() => {
                this.copiedDiagnostics = true;
                setTimeout(() => {
                    this.copiedDiagnostics = false;
                }, 2000);
            }).catch(() => {
                this.showToast('Could not access clipboard', 'error');
            });
        },

        showError(filename, message) {
            this.activeError = { filename, message };
            this.errorModalOpen = true;
        },

        destroy() {
            this.clearPollTimer();
        }
    }));
});
</script>
@endsection
