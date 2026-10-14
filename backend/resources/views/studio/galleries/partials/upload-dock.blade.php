{{-- Floating upload queue panel: compact summary that expands into the full per-file queue. --}}
<div x-show="uploads.length > 0"
     x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-3"
     class="fixed right-3 z-40 w-[calc(100vw-1.5rem)] transition-[bottom] duration-200 sm:right-6 sm:w-96"
     :class="(selectMode || selectedCount > 0) ? 'bottom-20' : 'bottom-4 sm:bottom-6'"
     role="status" aria-live="polite">
    <div class="overflow-hidden rounded-2xl border border-border bg-popover text-popover-foreground shadow-xl">
        <!-- Summary -->
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-full"
                 :class="isUploading ? 'bg-brand/10 text-brand' : (failedCount > 0 ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success')">
                <span x-show="isUploading"><x-icon name="loader-circle" class="size-4 animate-spin" /></span>
                <span x-show="!isUploading && failedCount > 0"><x-icon name="circle-alert" class="size-4" /></span>
                <span x-show="!isUploading && failedCount === 0"><x-icon name="check" class="size-4" stroke-width="2.5" /></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="flex items-center justify-between gap-2 text-[13px] font-semibold text-foreground">
                    <span class="truncate" x-text="isUploading ? 'Uploading ' + (completedCount + 1 > totalCount ? totalCount : completedCount + 1) + ' of ' + totalCount : (failedCount > 0 ? failedCount + ' upload' + (failedCount > 1 ? 's' : '') + ' need attention' : 'Uploads complete')">Uploading</span>
                    <span class="shrink-0 font-mono text-[11px] font-medium tabular-nums text-muted-foreground" x-text="overallProgress + '%'"></span>
                </p>
                <p class="mt-0.5 flex items-center gap-1.5 truncate text-[11px] text-muted-foreground">
                    <span class="tabular-nums" x-text="completedCount + ' of ' + totalCount + ' saved'"></span>
                    <template x-if="isUploading && formattedSpeed">
                        <span class="tabular-nums">· <span x-text="formattedSpeed"></span></span>
                    </template>
                    <template x-if="isUploading && etaText">
                        <span class="tabular-nums">· <span x-text="etaText"></span></span>
                    </template>
                </p>
            </div>
            <div class="flex shrink-0 items-center">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="queueExpanded = !queueExpanded" :aria-expanded="queueExpanded" :title="queueExpanded ? 'Collapse' : 'Show files'" aria-label="Toggle file list">
                    <span class="transition-transform duration-200" :class="queueExpanded ? 'rotate-180' : ''"><x-icon name="chevron-up" /></span>
                </button>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" x-show="!isUploading" @click="dismissQueue()" title="Dismiss" aria-label="Dismiss upload panel">
                    <x-icon name="x" />
                </button>
            </div>
        </div>
        <div class="px-4 pb-3" x-show="isUploading || overallProgress < 100">
            <div class="progress !h-1"><span :class="failedCount > 0 && !isUploading ? '!bg-destructive' : ''" :style="'width: ' + overallProgress + '%'"></span></div>
        </div>

        <!-- Quota hint -->
        <template x-if="quotaErrorState || (batchQuotaWarning && !quotaErrorState)">
            <button type="button" @click="$dispatch('open-modal', 'upload-photos-modal')"
                    class="flex w-full items-center gap-2 border-t border-border bg-warning/8 px-4 py-2 text-left text-xs font-medium text-warning transition-colors hover:bg-warning/12">
                <x-icon name="triangle-alert" class="size-3.5" />
                <span class="flex-1" x-text="quotaErrorState ? 'Storage full. Uploads are paused.' : 'This batch is larger than your remaining storage.'"></span>
                <span class="inline-flex items-center gap-0.5">Resolve <x-icon name="chevron-right" class="size-3.5" /></span>
            </button>
        </template>

        <!-- Expanded list -->
        <div x-show="queueExpanded" x-collapse.duration.200ms class="border-t border-border">
            <div class="max-h-72 divide-y divide-border overflow-y-auto px-4">
                <template x-for="item in uploads" :key="item.id">
                    @include('studio.galleries.partials.upload-item')
                </template>
            </div>
            <div class="flex items-center justify-between gap-2 border-t border-border bg-muted/40 px-4 py-2">
                <template x-if="failedCount > 0">
                    <button type="button" class="btn btn-ghost btn-xs" @click="retryAllFailed()">
                        <x-icon name="rotate-ccw" /> Retry failed (<span x-text="failedCount"></span>)
                    </button>
                </template>
                <span x-show="failedCount === 0"></span>
                <button type="button" class="btn btn-ghost btn-xs" @click="$dispatch('open-modal', 'upload-photos-modal')">
                    Upload details <x-icon name="arrow-up-right" />
                </button>
            </div>
        </div>
    </div>
</div>
