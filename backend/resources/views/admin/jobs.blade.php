@extends('layouts.admin', ['title' => 'Processing Queue & Exports - Admin'])

@section('content')
<div class="space-y-6"
     x-data="adminJobMonitor({
         initialMetrics: @js($metrics),
         initialMediaJobs: @js($initialMediaJobs),
         initialExportJobs: @js($initialExportJobs),
         initialGalleryBatches: @js($initialGalleryBatches ?? []),
         statusFilter: '{{ $statusFilter ?? '' }}',
         searchFilter: '{{ $searchFilter ?? '' }}'
     })"
     x-init="initPolling()"
     @beforeunload.window="destroy()">

    <!-- Toast Notification Banner -->
    <div x-show="toastMessage"
         x-transition
         class="p-4 rounded-xl text-xs font-semibold flex items-center justify-between shadow-md"
         :class="toastType === 'success' ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-destructive/15 border border-destructive/30 text-destructive'"
         style="display: none;">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="toastType === 'success'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                <path x-show="toastType !== 'success'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span x-text="toastMessage"></span>
        </div>
        <button @click="toastMessage = null" class="opacity-70 hover:opacity-100 cursor-pointer text-base leading-none">&times;</button>
    </div>

    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <span>Processing Queue & Exports</span>
                <span x-show="metrics.has_active_jobs"
                      class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                    Active Processing
                </span>
            </h1>
            <p class="text-xs text-muted-foreground mt-1">
                Live platform background jobs for WebP photo optimization, archive packaging, and cloud exports.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Redis Queue Backlog Indicator -->
            <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-card border border-border text-xs">
                <span class="w-2 h-2 rounded-full" :class="(metrics.queue_backlog?.photos ?? 0) > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                <span class="text-muted-foreground font-medium">Redis Queue:</span>
                <span class="font-bold text-foreground font-mono" x-text="(metrics.queue_backlog?.photos ?? 0) + ' photos / ' + (metrics.queue_backlog?.default ?? 0) + ' default'"></span>
            </div>

            <!-- Retry All Failed -->
            <button @click="retryFailed()"
                    :disabled="actionLoading || (!metrics.failed_total && !metrics.failed_today)"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-destructive/10 hover:bg-destructive/20 text-destructive border border-destructive/20 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    title="Re-queue all failed photo optimization jobs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Retry Failed (<span x-text="metrics.failed_total || metrics.failed_today || 0"></span>)</span>
            </button>

            <!-- Re-dispatch Queued -->
            <button @click="retryAllQueued()"
                    :disabled="actionLoading || metrics.queued === 0"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/20 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    title="Push all queued database records to Redis worker queue">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Push Queued (<span x-text="metrics.queued || 0"></span>)</span>
            </button>

            <!-- Restart Workers -->
            <button @click="restartWorkers()"
                    :disabled="actionLoading"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-secondary hover:bg-secondary/80 text-foreground border border-border transition-all disabled:opacity-40 cursor-pointer"
                    title="Signal queue workers to restart gracefully">
                <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Restart Workers</span>
            </button>

            <!-- Sync Live -->
            <button @click="pollNow(true)"
                    :disabled="polling"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-primary text-primary-foreground hover:bg-primary/90 transition-all disabled:opacity-50 cursor-pointer">
                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': polling }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span x-text="polling ? 'Syncing...' : 'Sync Live'"></span>
            </button>
        </div>
    </div>

    <!-- Operational Metrics Grid (Clickable Filters) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- Queued -->
        <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'queued'])) }}"
           class="p-4 rounded-2xl border bg-card shadow-sm space-y-1 block hover:border-primary transition-all cursor-pointer {{ $statusFilter === 'queued' ? 'ring-2 ring-primary border-primary' : 'border-border' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                <span>Queued</span>
                <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-2xl font-black text-foreground" x-text="metrics.queued"></p>
            <p class="text-[10px] text-muted-foreground">Waiting for worker &rarr;</p>
        </a>

        <!-- Processing -->
        <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'processing'])) }}"
           class="p-4 rounded-2xl border bg-card shadow-sm space-y-1 block hover:border-primary transition-all cursor-pointer {{ $statusFilter === 'processing' ? 'ring-2 ring-primary border-primary' : 'border-border' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-primary flex items-center justify-between">
                <span>Processing</span>
                <svg class="w-4 h-4 text-primary" :class="{ 'animate-spin': metrics.processing > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>
            <p class="text-2xl font-black text-primary" x-text="metrics.processing"></p>
            <p class="text-[10px] text-muted-foreground">Actively optimizing &rarr;</p>
        </a>

        <!-- Failed Today -->
        <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'failed'])) }}"
           class="p-4 rounded-2xl border bg-card shadow-sm space-y-1 block hover:border-destructive transition-all cursor-pointer {{ $statusFilter === 'failed' ? 'ring-2 ring-destructive border-destructive' : 'border-border' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-destructive flex items-center justify-between">
                <span>Failed</span>
                <svg class="w-4 h-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-2xl font-black text-destructive" x-text="metrics.failed_total ?? metrics.failed_today"></p>
                <span x-show="metrics.failed_today !== undefined" class="text-[10px] text-muted-foreground font-semibold" x-text="'(' + metrics.failed_today + ' today)'"></span>
            </div>
            <p class="text-[10px] text-muted-foreground">Click to inspect &rarr;</p>
        </a>

        <!-- Completed Today -->
        <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'completed'])) }}"
           class="p-4 rounded-2xl border bg-card shadow-sm space-y-1 block hover:border-emerald-500 transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'ring-2 ring-emerald-500 border-emerald-500' : 'border-border' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center justify-between">
                <span>Completed Today</span>
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400" x-text="metrics.completed_today"></p>
            <p class="text-[10px] text-muted-foreground">Since midnight &rarr;</p>
        </a>

        <!-- Active Exports -->
        <div @click="activeTab = 'exports'; window.location.hash = 'exports'"
             class="p-4 rounded-2xl border border-border bg-card shadow-sm space-y-1 col-span-2 sm:col-span-1 hover:border-blue-500 transition-all cursor-pointer">
            <div class="text-[11px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center justify-between">
                <span>Active Exports</span>
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </div>
            <p class="text-2xl font-black text-blue-600 dark:text-blue-400" x-text="metrics.active_exports"></p>
            <p class="text-[10px] text-muted-foreground">ZIPs & Google Syncs &rarr;</p>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="flex items-center gap-2 border-b border-border pb-px">
        <button @click="activeTab = 'jobs'; window.location.hash = 'jobs'"
                :class="activeTab === 'jobs' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground font-medium'"
                class="px-4 py-2.5 text-sm border-b-2 transition-all flex items-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Photo Processing Queue</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-foreground font-semibold">
                {{ $mediaJobs->total() }}
            </span>
        </button>

        <button @click="activeTab = 'exports'; window.location.hash = 'exports'"
                :class="activeTab === 'exports' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground font-medium'"
                class="px-4 py-2.5 text-sm border-b-2 transition-all flex items-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Exports Monitor</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-foreground font-semibold">
                {{ $exportJobs->total() }}
            </span>
        </button>
    </div>

    <!-- TAB 1: Photo Processing Queue -->
    <div x-show="activeTab === 'jobs'" class="space-y-4">
        <!-- View Mode Switcher & Filter Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-card p-3 rounded-2xl border border-border">
            <div class="flex items-center gap-1.5 text-xs font-semibold">
                <button @click="viewMode = 'batches'"
                        type="button"
                        :class="viewMode === 'batches' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span>By Gallery / Batch</span>
                    <span x-show="galleryBatches.length > 0"
                          class="px-1.5 py-0.2 rounded-full text-[10px] bg-primary/15 text-primary font-bold"
                          x-text="galleryBatches.length"></span>
                </button>
                <button @click="viewMode = 'stream'"
                        type="button"
                        :class="viewMode === 'stream' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Individual Photos Stream</span>
                </button>
            </div>

            <!-- Quick Status Filters -->
            <div class="flex items-center gap-1.5 text-xs font-medium overflow-x-auto">
                <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter])) }}"
                   class="px-3 py-1.5 rounded-lg {{ empty($statusFilter) ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    All
                </a>
                <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'queued'])) }}"
                   class="px-3 py-1.5 rounded-lg {{ $statusFilter === 'queued' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Queued
                </a>
                <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'processing'])) }}"
                   class="px-3 py-1.5 rounded-lg {{ $statusFilter === 'processing' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Processing
                </a>
                <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'completed'])) }}"
                   class="px-3 py-1.5 rounded-lg {{ $statusFilter === 'completed' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Completed
                </a>
                <a href="{{ route('admin.jobs.index', array_filter(['search' => $searchFilter, 'status' => 'failed'])) }}"
                   class="px-3 py-1.5 rounded-lg {{ $statusFilter === 'failed' ? 'bg-secondary text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Failed
                </a>
            </div>
        </div>

        <!-- VIEW 1: Gallery / Upload Batch Mode -->
        <div x-show="viewMode === 'batches'" class="space-y-4">
            <template x-for="batch in galleryBatches" :key="batch.gallery_id">
                <div class="p-5 rounded-2xl border border-border bg-card shadow-sm space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-base text-foreground" x-text="batch.gallery_title"></h4>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-muted-foreground font-mono" x-text="batch.total_photos + ' total photos'"></span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Photographer: <span class="font-semibold text-foreground" x-text="batch.studio_name"></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="retryGallery(batch.gallery_id)"
                                    :disabled="actionLoading"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 transition-all cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Retry Gallery Pending</span>
                            </button>
                            <a :href="'{{ route('admin.jobs.index') }}?search=' + encodeURIComponent(batch.gallery_title)"
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-secondary hover:bg-secondary/80 text-foreground border border-border transition-all">
                                Inspect Photos &rarr;
                            </a>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-foreground" x-text="batch.progress_percentage + '% Processed (' + batch.completed_photos + '/' + batch.total_photos + ')'"></span>
                            <div class="flex items-center gap-3 text-[11px] font-mono">
                                <span x-show="batch.queued_photos > 0" class="text-amber-600 dark:text-amber-400 font-bold" x-text="batch.queued_photos + ' Queued'"></span>
                                <span x-show="batch.processing_photos > 0" class="text-primary font-bold animate-pulse" x-text="batch.processing_photos + ' Processing'"></span>
                                <span x-show="batch.failed_photos > 0" class="text-destructive font-bold" x-text="batch.failed_photos + ' Failed'"></span>
                            </div>
                        </div>
                        <div class="w-full bg-secondary rounded-full h-2.5 overflow-hidden flex">
                            <div class="bg-emerald-500 h-full transition-all duration-300" :style="'width: ' + batch.progress_percentage + '%'"></div>
                            <div x-show="batch.processing_photos > 0" class="bg-primary h-full animate-pulse" :style="'width: ' + ((batch.processing_photos / Math.max(1, batch.total_photos)) * 100) + '%'"></div>
                            <div x-show="batch.failed_photos > 0" class="bg-destructive h-full" :style="'width: ' + ((batch.failed_photos / Math.max(1, batch.total_photos)) * 100) + '%'"></div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="galleryBatches.length === 0" class="p-12 text-center text-xs text-muted-foreground bg-card rounded-2xl border border-border">
                No active or pending gallery uploads right now. All galleries are up to date!
            </div>
        </div>

        <!-- VIEW 2: Individual Photos Stream Mode -->
        <div x-show="viewMode === 'stream'" class="space-y-4">
            <!-- Search Bar -->
            <form method="GET" action="{{ route('admin.jobs.index') }}" class="w-full sm:w-80">
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ $searchFilter }}"
                           placeholder="Search filename, photo UUID, studio..."
                           class="w-full rounded-xl border border-border bg-card px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                    @if($searchFilter)
                        <a href="{{ route('admin.jobs.index', array_filter(['status' => $statusFilter])) }}" class="absolute right-3 top-2 text-xs text-muted-foreground hover:text-foreground">
                            &times;
                        </a>
                    @endif
                </div>
            </form>

            <!-- Media Jobs Table -->
            <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                            <tr>
                                <th class="px-6 py-4">Media Job</th>
                                <th class="px-6 py-4">Studio & Gallery</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Stage Progress</th>
                                <th class="px-6 py-4 text-center">Attempts</th>
                                <th class="px-6 py-4">Duration</th>
                                <th class="px-6 py-4">Timestamps</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="job in mediaJobs" :key="job.id">
                                <tr class="hover:bg-muted/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-foreground truncate max-w-[220px]" :title="job.original_filename" x-text="job.original_filename"></div>
                                        <div class="text-[11px] text-muted-foreground font-mono mt-0.5 truncate max-w-[220px]" x-text="job.photo_uuid || 'N/A'"></div>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <div class="font-semibold text-foreground truncate max-w-[180px]" :title="job.gallery_title" x-text="job.gallery_title"></div>
                                        <div class="text-muted-foreground truncate max-w-[180px]" x-text="job.studio_name"></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400': job.status === 'completed',
                                                  'bg-primary/10 text-primary': job.status === 'processing',
                                                  'bg-destructive/10 text-destructive': job.status === 'failed',
                                                  'bg-secondary text-muted-foreground': job.status === 'queued'
                                              }">
                                            <span x-show="job.status === 'processing'" class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                            <span x-text="job.status"></span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <template x-if="job.status === 'failed'">
                                            <div class="flex items-center gap-2">
                                                <span class="text-destructive font-medium truncate max-w-[180px]" x-text="job.error_message || 'Execution failed'"></span>
                                                <button type="button"
                                                        @click="showError(job.original_filename, job.error_message || 'Unknown error')"
                                                        class="text-[10px] font-bold text-destructive underline hover:text-destructive/80 cursor-pointer shrink-0">
                                                    Details
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="job.status !== 'failed'">
                                            <span class="font-medium text-foreground" x-text="job.progress || (job.status === 'completed' ? 'Finalized' : 'Queued')"></span>
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 text-center text-xs font-mono font-semibold" x-text="job.attempts + ' / ' + job.max_attempts"></td>
                                    <td class="px-6 py-4 text-xs font-mono text-muted-foreground">
                                        <span x-show="job.duration_ms !== null && job.duration_ms !== undefined" x-text="formatDuration(job.duration_ms)"></span>
                                        <span x-show="job.duration_ms === null && job.status === 'processing' && job.started_at" class="text-primary italic">Active</span>
                                        <span x-show="job.duration_ms === null && !(job.status === 'processing' && job.started_at)">&mdash;</span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-muted-foreground space-y-0.5">
                                        <div x-show="job.started_at">
                                            <span class="font-semibold text-foreground">Start:</span>
                                            <span x-text="formatTime(job.started_at)"></span>
                                        </div>
                                        <div x-show="job.completed_at">
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">End:</span>
                                            <span x-text="formatTime(job.completed_at)"></span>
                                        </div>
                                        <div x-show="job.failed_at">
                                            <span class="font-semibold text-destructive">Fail:</span>
                                            <span x-text="formatTime(job.failed_at)"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button x-show="['failed', 'queued'].includes(job.status)"
                                                @click="retryJob(job.id)"
                                                :disabled="actionLoading"
                                                type="button"
                                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 transition-all cursor-pointer inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span>Retry</span>
                                        </button>
                                        <span x-show="job.status === 'completed'" class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">&check; Done</span>
                                        <span x-show="job.status === 'processing'" class="text-xs text-primary animate-pulse font-medium">Active</span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="mediaJobs.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-xs text-muted-foreground">
                                    No media processing jobs match the current filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if($mediaJobs->hasPages())
                    <div class="px-6 py-4 border-t border-border bg-card">
                        {{ $mediaJobs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB 2: Exports Monitor -->
    <div x-show="activeTab === 'exports'" class="space-y-4">
        <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                        <tr>
                            <th class="px-6 py-4">Export Type</th>
                            <th class="px-6 py-4">Studio & Gallery</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Progress</th>
                            <th class="px-6 py-4">Recipient Notification</th>
                            <th class="px-6 py-4">ETA / Duration</th>
                            <th class="px-6 py-4">Timestamps</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <template x-for="exportItem in exportJobs" :key="exportItem.type + '-' + exportItem.id">
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td class="px-6 py-4 font-semibold">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                          :class="exportItem.type === 'zip' ? 'bg-primary/10 text-primary' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-400'"
                                          x-text="exportItem.type === 'zip' ? 'ZIP Archive' : 'Google Photos'"></span>
                                    <div class="text-[10px] text-muted-foreground mt-1" x-text="'#' + exportItem.id"></div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <div class="font-semibold text-foreground truncate max-w-[200px]" :title="exportItem.gallery_title" x-text="exportItem.gallery_title"></div>
                                    <div class="text-muted-foreground truncate max-w-[200px]" x-text="exportItem.studio_name"></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400': ['ready', 'completed'].includes(exportItem.status),
                                              'bg-primary/10 text-primary': ['processing', 'pending'].includes(exportItem.status),
                                              'bg-destructive/10 text-destructive': exportItem.status === 'failed'
                                          }">
                                        <span x-show="['processing', 'pending'].includes(exportItem.status)" class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                        <span x-text="exportItem.status.replace('_', ' ')"></span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <template x-if="exportItem.indeterminate">
                                        <div class="flex items-center gap-2 text-muted-foreground">
                                            <svg class="w-3.5 h-3.5 animate-spin text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span class="italic">Syncing...</span>
                                        </div>
                                    </template>
                                    <template x-if="!exportItem.indeterminate">
                                        <div class="space-y-1.5 max-w-[160px]">
                                            <div class="flex justify-between text-[11px] font-semibold">
                                                <span x-text="exportItem.percentage + '%'"></span>
                                                <span class="text-muted-foreground font-mono text-[10px]" x-text="(exportItem.processed_photos + exportItem.failed_photos) + '/' + exportItem.total_photos"></span>
                                            </div>
                                            <div class="w-full bg-secondary rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-primary h-full rounded-full transition-all duration-300" :style="'width: ' + exportItem.percentage + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <template x-if="exportItem.email">
                                        <div class="flex items-center gap-1.5 text-foreground font-mono text-[11px]">
                                            <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span x-text="exportItem.email"></span>
                                        </div>
                                    </template>
                                    <template x-if="!exportItem.email">
                                        <span class="text-muted-foreground italic">No Alert</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <span x-text="formatEta(exportItem)"
                                          :class="exportItem.status === 'processing' && exportItem.remaining_seconds !== null ? 'text-primary font-mono font-bold' : 'text-muted-foreground font-mono'"></span>
                                </td>
                                <td class="px-6 py-4 text-xs text-muted-foreground space-y-0.5">
                                    <div>
                                        <span class="font-semibold text-foreground">Created:</span>
                                        <span x-text="formatDate(exportItem.created_at)"></span>
                                    </div>
                                    <div x-show="exportItem.completed_at">
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Done:</span>
                                        <span x-text="formatDate(exportItem.completed_at)"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="exportJobs.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-xs text-muted-foreground">
                                No packaging or cloud export jobs recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if($exportJobs->hasPages())
                <div class="px-6 py-4 border-t border-border bg-card">
                    {{ $exportJobs->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Error Detail Modal -->
    <div x-show="errorModalOpen"
         x-transition.opacity
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
         style="display: none;"
         @click.self="errorModalOpen = false">
        <div class="bg-card border border-border rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-destructive flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Processing Failure Detail</span>
                </h3>
                <button @click="errorModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg cursor-pointer">&times;</button>
            </div>

            <div class="space-y-2">
                <div class="text-xs text-muted-foreground font-semibold uppercase">Target Photo:</div>
                <div class="text-xs font-mono font-bold text-foreground" x-text="activeError?.filename"></div>
            </div>

            <div class="space-y-2">
                <div class="text-xs text-muted-foreground font-semibold uppercase">Error Message:</div>
                <div class="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-mono whitespace-pre-wrap break-words leading-relaxed"
                     x-text="activeError?.message"></div>
            </div>

            <div class="pt-2 flex justify-end">
                <button @click="errorModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-secondary hover:bg-secondary/80 text-foreground text-xs font-semibold cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

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
        actionLoading: false,
        toastMessage: null,
        toastType: 'success',
        toastTimeout: null,

        csrfToken() {
            return '{{ csrf_token() }}';
        },

        showToast(message, type = 'success') {
            this.toastMessage = message;
            this.toastType = type;
            if (this.toastTimeout) clearTimeout(this.toastTimeout);
            this.toastTimeout = setTimeout(() => {
                this.toastMessage = null;
            }, 6000);
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
                }
                if (data.exports) {
                    this.exportJobs = data.exports;
                }
                if (data.gallery_batches) {
                    this.galleryBatches = data.gallery_batches;
                }
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
