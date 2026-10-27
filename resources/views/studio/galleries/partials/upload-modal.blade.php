{{-- Upload Photos & Videos Modal (open with $dispatch('open-modal', 'upload-photos-modal')) --}}
<x-ui.modal name="upload-photos-modal" title="Upload photos & videos" description="Files upload straight to secure storage and appear in the grid as they finish." maxWidth="2xl">
    <div class="space-y-4">

        <!-- Upgraded Plan Celebration Banner -->
        <template x-if="upgradedCelebration">
            <div class="alert alert-success flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <x-icon name="sparkles" class="mt-0.5 size-4" />
                    <div>
                        <p class="text-[13px] font-semibold">Plan upgraded</p>
                        <p class="mt-0.5 text-xs opacity-90">
                            You're now on <strong x-text="upgradedCelebration.planName"></strong> with <strong x-text="upgradedCelebration.storageLimitFormatted"></strong> of storage. Unfinished uploads have been resumed.
                        </p>
                    </div>
                </div>
                <button type="button" @click="upgradedCelebration = null" class="btn btn-ghost btn-icon btn-xs -mr-1 -mt-1" aria-label="Dismiss"><x-icon name="x" /></button>
            </div>
        </template>

        <!-- Pre-flight Batch Quota Warning -->
        <template x-if="batchQuotaWarning && !quotaErrorState">
            <div class="alert alert-warning flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <x-icon name="triangle-alert" class="mt-0.5 size-4" />
                    <div>
                        <p class="text-[13px] font-semibold">Selected files exceed remaining storage</p>
                        <p class="mt-0.5 text-xs opacity-90">
                            This batch is <strong x-text="batchQuotaWarning.formattedBatchSize"></strong>, but only <strong x-text="batchQuotaWarning.formattedAvailable"></strong> is left in your plan.
                        </p>
                    </div>
                </div>
                <button type="button" x-show="canManageBilling" @click="openUpgradeWindow()" class="btn btn-outline btn-sm shrink-0">
                    Upgrade plan <x-icon name="arrow-up-right" />
                </button>
                <p x-show="!canManageBilling" class="text-xs opacity-90 sm:max-w-48 sm:text-right">Ask the studio owner to upgrade the plan.</p>
            </div>
        </template>

        <!-- Active Quota Exhaustion Card -->
        <template x-if="quotaErrorState">
            <div class="overflow-hidden rounded-xl border border-destructive/30 bg-destructive/5">
                <div class="flex items-start justify-between gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                            <x-icon name="hard-drive" class="size-4" />
                        </div>
                        <div>
                            <h4 class="text-[13px] font-semibold text-foreground">Storage quota reached</h4>
                            <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                You've reached the <span class="font-medium text-foreground" x-text="quotaErrorState.formattedLimit"></span> limit on your current plan. Uploads are paused and will pick up where they left off.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="quotaErrorState = null" class="btn btn-ghost btn-icon btn-xs -mr-1 -mt-1" aria-label="Dismiss"><x-icon name="x" /></button>
                </div>

                <div class="space-y-3 border-t border-destructive/15 bg-card p-4">
                    <!-- Storage Mini Bar Breakdown -->
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Remaining capacity</span>
                            <span class="font-mono font-medium text-destructive" x-text="quotaErrorState.formattedAvailable + ' available'"></span>
                        </div>
                        <div class="progress"><span class="!bg-destructive" style="width: 100%"></span></div>
                        <p class="text-[11px] text-muted-foreground">
                            This file needs <span class="font-mono font-medium text-foreground" x-text="quotaErrorState.formattedRequired"></span>.
                            <span x-show="!canManageBilling">Ask the studio owner to upgrade the plan, or free up space.</span>
                        </p>
                    </div>

                    <!-- Choose Plan Section -->
                    <template x-if="upgradePlans && upgradePlans.length > 0">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-foreground">Choose a plan that fits your studio</span>
                                <a :href="billingPlansUrl" target="_blank" class="link inline-flex items-center gap-1 text-xs">
                                    Compare all <x-icon name="arrow-up-right" class="size-3" />
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <template x-for="plan in upgradePlans" :key="plan.slug">
                                    <button type="button"
                                            @click="openUpgradeWindow(plan.checkoutUrl)"
                                            class="group flex items-center justify-between rounded-lg border border-border bg-card p-3 text-left transition-colors hover:border-foreground/30 hover:bg-muted/50">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[13px] font-semibold text-foreground" x-text="plan.name"></span>
                                                <span class="badge badge-neutral font-mono !text-[10px]" x-text="plan.storage"></span>
                                            </div>
                                            <p class="mt-0.5 text-[11px] text-muted-foreground" x-text="plan.price + ' / mo · ' + plan.video_limit"></p>
                                        </div>
                                        <x-icon name="arrow-up-right" class="size-4 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-foreground" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Check Status Feedback Notification (if any) -->
                    <template x-if="checkStatusFeedback">
                        <div class="alert flex items-center justify-between gap-2 !py-2 text-xs"
                             :class="{
                                 'alert-warning': checkStatusFeedback.type === 'warning',
                                 'alert-success': checkStatusFeedback.type === 'success',
                                 'alert-danger': checkStatusFeedback.type === 'error'
                             }">
                            <div class="flex items-center gap-2">
                                <span x-show="checkStatusFeedback.type === 'success'"><x-icon name="circle-check" class="size-4" /></span>
                                <span x-show="checkStatusFeedback.type !== 'success'"><x-icon name="triangle-alert" class="size-4" /></span>
                                <span x-text="checkStatusFeedback.message"></span>
                            </div>
                            <button type="button" @click="checkStatusFeedback = null" class="opacity-60 hover:opacity-100" aria-label="Dismiss"><x-icon name="x" class="size-3.5" /></button>
                        </div>
                    </template>

                    <!-- Action Buttons: Upgrade Plan & Free Up Storage -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
                        <a href="{{ route('studio.trash.index') }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground">
                            <x-icon name="trash-2" class="size-3.5" /> Free up space (empty trash) <x-icon name="arrow-up-right" class="size-3" />
                        </a>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="checkAndApplyUpgradedStorage(false, true)"
                                    :disabled="isCheckingStatus"
                                    class="btn btn-outline btn-sm">
                                <template x-if="isCheckingStatus">
                                    <x-icon name="loader-circle" class="animate-spin" />
                                </template>
                                <template x-if="!isCheckingStatus">
                                    <x-icon name="refresh-cw" />
                                </template>
                                <span x-text="isCheckingStatus ? 'Checking…' : 'Check status'"></span>
                            </button>
                            <button type="button" x-show="canManageBilling" @click="openUpgradeWindow(billingPlansUrl)" class="btn btn-primary btn-sm">
                                Compare plans <x-icon name="arrow-up-right" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Dropzone Area -->
        <div class="group/dz relative flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-all hover:border-foreground/30 hover:bg-muted/40"
             :class="isDragging ? 'border-brand bg-brand/5 ring-4 ring-brand/10' : 'border-border-strong/70'"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; addFiles($event.dataTransfer.files)">
            <input type="file"
                   id="photo-file-input"
                   multiple
                   accept="image/jpeg,image/png,image/webp,image/avif,video/mp4,video/quicktime,video/webm"
                   class="absolute inset-0 z-10 size-full cursor-pointer opacity-0"
                   aria-label="Choose photos and videos to upload"
                   @change="handleFileInput($event)">
            <div class="pointer-events-none mb-3 flex size-12 items-center justify-center rounded-xl border border-border bg-card text-brand shadow-sm transition-transform group-hover/dz:-translate-y-0.5">
                <x-icon name="cloud-upload" class="size-6" />
            </div>
            <p class="pointer-events-none text-sm font-semibold text-foreground">
                <span class="underline decoration-border-strong underline-offset-4">Browse files</span> or drag and drop
            </p>
            <p class="pointer-events-none mt-1 text-xs text-muted-foreground">Photos up to 50 MB · MP4, MOV and WebM videos up to your plan limit</p>
        </div>

        <!-- Global Status Alert Banner -->
        <template x-if="statusSummary">
            <div class="alert flex items-center justify-between gap-2 text-xs"
                 :class="{
                     'alert-success': statusSummary.type === 'success',
                     'alert-warning': statusSummary.type === 'warning',
                     'alert-danger': statusSummary.type === 'error'
                 }">
                <span x-text="statusSummary.message" class="font-medium"></span>
                <button type="button" @click="statusSummary = null" class="opacity-70 hover:opacity-100" aria-label="Dismiss"><x-icon name="x" class="size-3.5" /></button>
            </div>
        </template>

        <!-- Overall Queue Progress -->
        <template x-if="uploads.length > 0">
            <div class="rounded-xl border border-border bg-muted/30 p-3.5">
                <div class="flex items-center justify-between gap-3 text-xs">
                    <span class="text-muted-foreground">
                        <span class="font-semibold tabular-nums text-foreground" x-text="completedCount"></span> of <span class="tabular-nums" x-text="totalCount"></span> saved
                        <template x-if="isUploading && formattedSpeed">
                            <span class="tabular-nums"> · <span x-text="formattedSpeed"></span></span>
                        </template>
                        <template x-if="isUploading && etaText">
                            <span class="tabular-nums"> · <span x-text="etaText"></span></span>
                        </template>
                    </span>
                    <div class="flex items-center gap-2">
                        <template x-if="failedCount > 0">
                            <button type="button" @click="retryAllFailed()" class="btn btn-ghost btn-xs">
                                <x-icon name="rotate-ccw" /> Retry failed (<span x-text="failedCount"></span>)
                            </button>
                        </template>
                        <span class="font-mono tabular-nums text-muted-foreground" x-text="overallProgress + '%'"></span>
                    </div>
                </div>
                <div class="progress mt-2.5"><span :style="'width: ' + overallProgress + '%'"></span></div>
            </div>
        </template>

        <!-- Active Uploads List -->
        <template x-if="uploads.length > 0">
            <div class="-mx-1 max-h-64 divide-y divide-border overflow-y-auto px-1">
                <template x-for="item in uploads" :key="item.id">
                    @include('studio.galleries.partials.upload-item')
                </template>
            </div>
        </template>

        <!-- Storage summary + Modal Actions Footer -->
        <div class="flex flex-col-reverse gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <x-icon name="shield-check" class="size-3.5 text-success" />
                You can keep working while files upload.
            </p>
            <div class="flex items-center justify-end gap-2">
                <template x-if="completedCount > 0">
                    <button type="button" @click="refreshGallery()" class="btn btn-outline btn-sm">
                        <x-icon name="refresh-cw" /> Refresh gallery
                    </button>
                </template>
                <button type="button" @click="$dispatch('close-modal', 'upload-photos-modal')" class="btn btn-primary btn-sm">
                    Done
                </button>
            </div>
        </div>
    </div>
</x-ui.modal>
