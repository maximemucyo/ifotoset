{{-- One row of the upload queue. Rendered inside `<template x-for="item in uploads">`. --}}
<div class="flex items-center gap-3 py-2.5">
    <!-- Preview Thumbnail / state -->
    <div class="relative flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted ring-1 ring-border">
        <template x-if="item.previewUrl && !item.isVideo">
            <img :src="item.previewUrl" alt="" class="size-full object-cover">
        </template>
        <template x-if="!(item.previewUrl && !item.isVideo) && item.status !== 'done' && item.status !== 'error'">
            <span class="text-subtle">
                <span x-show="item.isVideo"><x-icon name="video" class="size-4" /></span>
                <span x-show="!item.isVideo"><x-icon name="image" class="size-4" /></span>
            </span>
        </template>
        <template x-if="item.status === 'done'">
            <div class="flex size-full items-center justify-center bg-success/10 text-success">
                <x-icon name="check" class="size-4" stroke-width="2.5" />
            </div>
        </template>
        <template x-if="item.status === 'error'">
            <div class="flex size-full items-center justify-center bg-destructive/10 text-destructive">
                <x-icon name="circle-alert" class="size-4" />
            </div>
        </template>
    </div>

    <!-- Name & Status Info -->
    <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
            <p class="truncate text-[13px] font-medium text-foreground" x-text="item.name" :title="item.name"></p>
            <span class="shrink-0 font-mono text-[10px] text-subtle" x-text="item.formattedSize"></span>
        </div>

        <div class="mt-1">
            <template x-if="item.status === 'uploading'">
                <div class="flex items-center gap-2">
                    <div class="progress !h-1 flex-1"><span :style="'width: ' + item.progress + '%'"></span></div>
                    <span class="w-8 shrink-0 text-right font-mono text-[10px] tabular-nums text-muted-foreground" x-text="item.progress + '%'"></span>
                </div>
            </template>

            <template x-if="item.status === 'preparing'">
                <p class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <span class="size-1.5 animate-pulse rounded-full bg-brand"></span>
                    Preparing secure upload…
                </p>
            </template>

            <template x-if="item.status === 'processing'">
                <p class="flex items-center gap-1.5 text-[11px] font-medium text-info">
                    <x-icon name="loader-circle" class="size-3 animate-spin" />
                    <span x-text="item.isVideo ? 'Optimizing video stream…' : 'Saving to gallery…'"></span>
                </p>
            </template>

            <template x-if="item.status === 'retrying'">
                <p class="flex items-center gap-1.5 text-[11px] font-medium text-warning">
                    <x-icon name="refresh-cw" class="size-3 animate-spin" />
                    <span class="truncate" x-text="item.errorMessage || 'Retrying upload…'"></span>
                </p>
            </template>

            <template x-if="item.status === 'queued'">
                <p class="flex items-center gap-1.5 text-[11px] text-subtle"><x-icon name="clock" class="size-3" /> Waiting in queue</p>
            </template>

            <template x-if="item.status === 'done'">
                <p class="flex items-center gap-1.5 text-[11px] font-medium text-success"><x-icon name="circle-check" class="size-3" /> Saved to gallery</p>
            </template>

            <template x-if="item.status === 'error'">
                <p class="truncate text-[11px] font-medium text-destructive" x-text="item.errorMessage || 'Upload interrupted'" :title="item.errorMessage"></p>
            </template>

            <template x-if="item.status === 'cancelled'">
                <p class="text-[11px] italic text-subtle">Cancelled</p>
            </template>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex shrink-0 items-center">
        <template x-if="item.status === 'error'">
            <button type="button"
                    @click="retryUpload(item.id)"
                    class="btn btn-ghost btn-icon btn-xs"
                    title="Retry upload" aria-label="Retry upload">
                <x-icon name="rotate-ccw" />
            </button>
        </template>

        <template x-if="item.status === 'queued' || item.status === 'preparing' || item.status === 'uploading' || item.status === 'retrying'">
            <button type="button"
                    @click="cancelUpload(item.id)"
                    class="btn btn-ghost btn-icon btn-xs text-muted-foreground"
                    title="Cancel" aria-label="Cancel upload">
                <x-icon name="x" />
            </button>
        </template>
    </div>
</div>
