@extends('layouts.app', ['title' => $gallery->title . ' - Studio Photo Manager'])

@section('content')
<div class="space-y-8 relative" x-data="galleryUploader('{{ $gallery->uuid }}', '{{ route('studio.uploads.request') }}', '{{ route('studio.uploads.confirm') }}', '{{ route('studio.uploads.abort') }}', '{{ csrf_token() }}')">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('studio.galleries.index') }}" class="text-xs font-semibold text-muted-foreground hover:text-foreground">
                    &larr; Galleries
                </a>
                <span class="text-xs text-muted-foreground">&bull;</span>
                <x-ui.badge :variant="$gallery->visibility === 'public' ? 'default' : ($gallery->visibility === 'password' ? 'warning' : 'muted')">
                    {{ ucfirst($gallery->visibility) }}
                </x-ui.badge>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $gallery->title }}</h1>
            <p class="text-xs text-muted-foreground mt-1">
                {{ $photos->total() }} {{ Str::plural('photo', $photos->total()) }} &bull; Slug: <span class="font-mono text-primary">{{ $gallery->slug }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $gallery->public_url }}" target="_blank"
               class="px-3.5 py-2 rounded-xl bg-card border border-border text-foreground hover:bg-secondary text-xs font-semibold shadow-sm transition-all flex items-center gap-1.5">
                View Public Gallery &nearr;
            </a>
            <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}"
               class="px-3.5 py-2 rounded-xl bg-card border border-border text-foreground hover:bg-secondary text-xs font-semibold shadow-sm transition-all">
                Settings
            </a>
            <x-ui.button @click="$dispatch('open-modal', 'upload-photos-modal')" variant="primary" size="sm">
                + Upload Photos
            </x-ui.button>
        </div>
    </div>

    <!-- Upload Dropzone Hero (when gallery is empty) -->
    @if($photos->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-border p-12 text-center bg-card flex flex-col items-center justify-center transition-all hover:border-primary/50 cursor-pointer"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addFiles($event.dataTransfer.files)"
             @click="$dispatch('open-modal', 'upload-photos-modal')"
             :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : ''">
            <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold mb-4">
                📸
            </div>
            <h2 class="text-lg font-bold text-foreground">This collection is currently empty</h2>
            <p class="text-xs text-muted-foreground max-w-sm mt-1 mb-6">
                Drag and drop your high-resolution photos to upload and generate client previews.
            </p>
            <x-ui.button type="button" variant="primary">
                Select Photos to Upload
            </x-ui.button>
        </div>
    @else
        <!-- Photos Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="studio-photos-grid">
            @foreach($photos as $photo)
                @php
                    $thumbUrl = $photo->getUrl('sm');
                    $isCover = $gallery->cover_photo_id === $photo->id;
                @endphp
                <div class="group relative rounded-xl overflow-hidden bg-card border {{ $isCover ? 'border-primary ring-2 ring-primary/40' : 'border-border' }} shadow-sm flex flex-col justify-between">
                    <div class="aspect-square bg-muted relative overflow-hidden">
                        <img src="{{ $thumbUrl }}"
                             alt="{{ $photo->original_filename }}"
                             loading="lazy"
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">

                        @if($isCover)
                            <div class="absolute top-2 left-2">
                                <span class="px-2 py-0.5 rounded bg-primary text-primary-foreground text-[10px] font-bold uppercase tracking-wider shadow">Cover</span>
                            </div>
                        @endif

                        <!-- Hover Overlay Actions -->
                        <div class="absolute inset-0 bg-foreground/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2">
                            <div class="flex justify-end">
                                <form method="POST" action="{{ url('/api/v1/photos/' . $photo->uuid) }}" onsubmit="return confirm('Delete this photo?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 rounded bg-destructive text-white hover:opacity-90 text-xs" title="Delete Photo">
                                        &times;
                                    </button>
                                </form>
                            </div>

                            @if(!$isCover)
                                <form method="POST" action="{{ route('studio.galleries.cover', $gallery->uuid) }}">
                                    @csrf
                                    <input type="hidden" name="photo_id" value="{{ $photo->id }}">
                                    <button type="submit" class="w-full py-1 text-[11px] font-semibold bg-card text-foreground hover:bg-primary hover:text-primary-foreground rounded transition-colors text-center">
                                        Set as Cover
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="p-2 text-[11px] text-muted-foreground truncate border-t border-border">
                        {{ $photo->original_filename }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-6">
            {{ $photos->links() }}
        </div>
    @endif

    <!-- Floating Background Upload Status Badge (shown if modal is closed while uploads run) -->
    <div x-show="isUploading"
         x-transition
         class="fixed bottom-6 right-6 z-40 bg-card border border-border shadow-2xl rounded-2xl p-4 flex items-center gap-3.5 max-w-sm w-full"
         style="display: none;">
        <div class="relative flex items-center justify-center">
            <div class="w-7 h-7 rounded-full border-2 border-primary/20 border-t-primary animate-spin"></div>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-foreground flex items-center justify-between">
                <span>Uploading Photos</span>
                <span class="font-mono text-[11px] text-primary" x-text="completedCount + '/' + totalCount"></span>
            </p>
            <div class="h-1.5 bg-muted rounded-full overflow-hidden mt-1.5">
                <div class="h-full bg-primary rounded-full transition-all duration-300" :style="'width: ' + overallProgress + '%'"></div>
            </div>
        </div>
        <button type="button" @click="$dispatch('open-modal', 'upload-photos-modal')" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-secondary hover:bg-secondary/80 text-foreground transition-colors shrink-0">
            View
        </button>
    </div>

    <!-- Upload Photos Modal -->
    <x-ui.modal name="upload-photos-modal" title="Upload Photos" maxWidth="2xl">
        <div class="space-y-5">
            <p class="text-xs text-muted-foreground">
                Select photos to add to your collection. High-resolution images will be automatically optimized and prepared for your gallery.
            </p>

            <!-- Dropzone Area -->
            <div class="border-2 border-dashed rounded-2xl p-6 sm:p-8 transition-all relative flex flex-col items-center justify-center text-center bg-secondary/10 hover:bg-secondary/20 hover:border-primary/50 cursor-pointer"
                 :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : 'border-border'"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="isDragging = false; addFiles($event.dataTransfer.files)">
                <input type="file"
                       id="photo-file-input"
                       multiple
                       accept="image/jpeg,image/png,image/webp,image/avif"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                       @change="handleFileInput($event)">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-2xl mb-3 pointer-events-none">
                    📁
                </div>
                <p class="text-sm font-semibold text-foreground pointer-events-none">Click to browse or drag and drop photos</p>
                <p class="text-xs text-muted-foreground mt-1 pointer-events-none">JPEG, PNG, WebP up to 50MB per file</p>
            </div>

            <!-- Upload Queue Section (shown when files are selected) -->
            <div x-show="uploads.length > 0" class="bg-secondary/15 border border-border rounded-xl p-4 sm:p-5 space-y-4 shadow-sm" style="display: none;">
                <!-- Queue Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-bold text-sm text-foreground">Upload Queue</h4>
                            <span class="text-xs text-muted-foreground" x-text="'(' + completedCount + '/' + totalCount + ' completed)'"></span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">Please keep this page open until upload completes.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <template x-if="failedCount > 0">
                            <button type="button" @click="retryAllFailed()" class="text-xs font-semibold px-2.5 py-1 bg-destructive/10 text-destructive hover:bg-destructive/20 rounded-lg transition-colors flex items-center gap-1">
                                <span>↻</span> Retry Failed (<span x-text="failedCount"></span>)
                            </button>
                        </template>

                        <template x-if="activeCount > 0">
                            <span class="text-xs font-semibold px-2.5 py-1 bg-primary/10 text-primary rounded-full animate-pulse flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                                <span x-text="activeCount + ' Uploading'"></span>
                            </span>
                        </template>

                        <template x-if="activeCount === 0 && completedCount === totalCount && totalCount > 0">
                            <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-500/10 text-emerald-600 rounded-full flex items-center gap-1">
                                ✓ Done
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Overall Progress Bar -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] text-muted-foreground font-medium">
                        <span>Overall Progress</span>
                        <span class="font-mono font-semibold text-foreground" x-text="overallProgress + '%'"></span>
                    </div>
                    <div class="h-1.5 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300" :style="'width: ' + overallProgress + '%'"></div>
                    </div>
                </div>

                <!-- Scrollable List of All Files -->
                <div class="space-y-2.5 max-h-72 sm:max-h-80 overflow-y-auto divide-y divide-border/20 pr-1">
                    <template x-for="item in uploads" :key="item.id">
                        <div class="pt-2.5 first:pt-0 space-y-1.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <!-- Mini Thumbnail Preview -->
                                    <template x-if="item.previewUrl">
                                        <img :src="item.previewUrl" class="w-8 h-8 rounded object-cover shrink-0 bg-muted border border-border shadow-xs" alt="" />
                                    </template>
                                    <template x-if="!item.previewUrl">
                                        <div class="w-8 h-8 rounded bg-muted flex items-center justify-center text-xs shrink-0 text-muted-foreground border border-border">📷</div>
                                    </template>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-medium text-foreground truncate" :title="item.name" x-text="item.name"></p>
                                        <p class="text-[10px] text-muted-foreground font-mono" x-text="item.formattedSize"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <!-- Status Badges -->
                                    <template x-if="item.status === 'queued'">
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-secondary text-muted-foreground">Queued</span>
                                    </template>
                                    <template x-if="item.status === 'preparing'">
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-primary/10 text-primary animate-pulse">Preparing...</span>
                                    </template>
                                    <template x-if="item.status === 'uploading'">
                                        <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary animate-pulse" x-text="item.progress + '%'"></span>
                                    </template>
                                    <template x-if="item.status === 'processing'">
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-500 animate-pulse">Processing...</span>
                                    </template>
                                    <template x-if="item.status === 'done'">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600">✓ Uploaded</span>
                                    </template>
                                    <template x-if="item.status === 'error'">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-destructive/10 text-destructive">Failed</span>
                                    </template>
                                    <template x-if="item.status === 'cancelled'">
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-muted text-muted-foreground">Cancelled</span>
                                    </template>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-1">
                                        <template x-if="item.status === 'error'">
                                            <button type="button" @click="retryUpload(item.id)" class="p-1 rounded hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors text-xs font-bold" title="Retry upload">
                                                ↻
                                            </button>
                                        </template>
                                        <template x-if="item.status === 'queued' || item.status === 'preparing' || item.status === 'uploading'">
                                            <button type="button" @click="cancelUpload(item.id)" class="p-1 rounded hover:bg-secondary text-muted-foreground hover:text-destructive transition-colors text-xs font-bold" title="Cancel upload">
                                                &times;
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="h-1.5 bg-muted rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-200"
                                     :class="item.status === 'done' ? 'bg-emerald-500' : (item.status === 'error' ? 'bg-destructive' : (item.status === 'processing' ? 'bg-amber-500' : 'bg-primary'))"
                                     :style="'width: ' + (item.status === 'done' || item.status === 'error' ? 100 : item.progress) + '%'"></div>
                            </div>

                            <!-- Friendly Error Message (if any) -->
                            <template x-if="item.status === 'error' && item.errorMessage">
                                <p class="text-destructive text-[11px]" x-text="item.errorMessage"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Status Summary Banner -->
            <template x-if="statusSummary">
                <div :class="{
                    'bg-emerald-500/10 text-emerald-600 border-emerald-500/20': statusSummary.type === 'success',
                    'bg-amber-500/10 text-amber-500 border-amber-500/20': statusSummary.type === 'warning',
                    'bg-destructive/10 text-destructive border-destructive/20': statusSummary.type === 'error'
                }" class="text-xs font-semibold py-2.5 px-3.5 rounded-xl text-center border">
                    <span x-text="statusSummary.message"></span>
                </div>
            </template>

            <!-- Modal Actions Footer -->
            <div class="flex justify-end items-center gap-2 pt-2 border-t border-border">
                <template x-if="completedCount > 0">
                    <x-ui.button type="button" @click="refreshGallery()" variant="primary" size="sm">
                        Refresh Gallery
                    </x-ui.button>
                </template>
                <x-ui.button type="button" @click="$dispatch('close-modal', 'upload-photos-modal')" variant="outline" size="sm">
                    Close
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>

<script>
/**
 * Utility: Format bytes into human-readable string
 */
function formatBytes(bytes, decimals = 1) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Utility: Sanitize error messages to avoid leaking technical backend specs
 */
function cleanErrorMessage(msg) {
    if (!msg || typeof msg !== 'string') return 'Upload failed. Please retry.';
    const lower = msg.toLowerCase();
    if (lower.includes('413') || lower.includes('too large') || lower.includes('size')) {
        return 'Photo exceeds maximum allowed size (50MB).';
    }
    if (lower.includes('network') || lower.includes('failed to fetch') || lower.includes('connection')) {
        return 'Connection interrupted. Please retry.';
    }
    if (lower.includes('quota') || lower.includes('storage limit')) {
        return 'Storage quota reached for your account.';
    }
    if (lower.includes('b2') || lower.includes('backblaze') || lower.includes('s3') || lower.includes('bucket') || lower.includes('storage error')) {
        return 'Upload service unavailable. Please retry.';
    }
    if (lower.includes('blurhash')) {
        return 'Photo processing error. Please retry.';
    }
    return msg;
}

/**
 * Utility: Compute SHA-256 integrity checksum
 */
async function calculateFileSha256(file) {
    if (window.crypto && crypto.subtle) {
        try {
            const buffer = await file.arrayBuffer();
            const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        } catch (e) {
            console.warn('Subtle digest unavailable, using fallback', e);
        }
    }
    return '';
}

/**
 * Alpine Component: Gallery Photo Upload Manager with 4-at-a-time Concurrency
 */
window.galleryUploader = function(galleryUuid, requestUrl, confirmUrl, abortUrl, csrfToken) {
    const CONCURRENCY_LIMIT = 4;

    return {
        galleryUuid: galleryUuid,
        requestUrl: requestUrl,
        confirmUrl: confirmUrl,
        abortUrl: abortUrl,
        csrfToken: csrfToken,

        uploads: [],
        isDragging: false,
        statusSummary: null,

        get isUploading() {
            return this.uploads.some(u => u.status === 'queued' || u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing');
        },

        get activeCount() {
            return this.uploads.filter(u => u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing').length;
        },

        get completedCount() {
            return this.uploads.filter(u => u.status === 'done').length;
        },

        get failedCount() {
            return this.uploads.filter(u => u.status === 'error').length;
        },

        get totalCount() {
            return this.uploads.length;
        },

        get overallProgress() {
            if (!this.uploads.length) return 0;
            const total = this.uploads.reduce((acc, u) => {
                if (u.status === 'done') return acc + 100;
                if (u.status === 'error' || u.status === 'cancelled') return acc + 0;
                return acc + (u.progress || 0);
            }, 0);
            return Math.round(total / this.uploads.length);
        },

        handleFileInput(e) {
            if (e.target && e.target.files) {
                this.addFiles(e.target.files);
                e.target.value = '';
            }
        },

        addFiles(fileList) {
            if (!fileList || fileList.length === 0) return;

            const filesArray = Array.from(fileList).filter(f => f.type.startsWith('image/'));
            if (filesArray.length === 0) return;

            const newItems = filesArray.map(file => {
                let previewUrl = null;
                try {
                    previewUrl = URL.createObjectURL(file);
                } catch (_) {}

                return {
                    id: 'up-' + Math.random().toString(36).substring(2, 9) + '-' + Date.now(),
                    file: file,
                    name: file.name,
                    formattedSize: formatBytes(file.size),
                    previewUrl: previewUrl,
                    status: 'queued',
                    progress: 0,
                    errorMessage: '',
                    xhr: null,
                    sessionId: null
                };
            });

            this.statusSummary = null;
            this.uploads.push(...newItems);
            this.runQueue();
        },

        runQueue() {
            const active = this.uploads.filter(u => u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing');
            const availableSlots = CONCURRENCY_LIMIT - active.length;

            if (availableSlots <= 0) return;

            const queuedItems = this.uploads.filter(u => u.status === 'queued').slice(0, availableSlots);
            for (const item of queuedItems) {
                this.startUpload(item);
            }
        },

        async startUpload(item) {
            item.status = 'preparing';
            item.progress = 0;
            item.errorMessage = '';

            let sessionId = null;

            try {
                // 1. Calculate integrity hash
                const sha256 = await calculateFileSha256(item.file);
                if (item.status === 'cancelled') return;

                // 2. Request upload slot
                const reqRes = await fetch(this.requestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        gallery_id: this.galleryUuid,
                        gallery_uuid: this.galleryUuid,
                        filename: item.file.name,
                        file_size: item.file.size,
                        size_bytes: item.file.size,
                        mime_type: item.file.type || 'image/jpeg',
                        sha256: sha256
                    })
                });

                if (!reqRes.ok) {
                    const errData = await reqRes.json().catch(() => ({}));
                    throw new Error(errData.message || 'Unable to prepare photo upload');
                }

                const reqData = await reqRes.json();
                const presignedUrl = reqData.presigned_url || reqData.upload_url;
                sessionId = reqData.upload_session_id || reqData.session_id;
                item.sessionId = sessionId;
                const extraHeaders = reqData.headers || {};

                if (!presignedUrl || !sessionId) {
                    throw new Error('Invalid upload session');
                }

                if (item.status === 'cancelled') {
                    this.abortSession(sessionId);
                    return;
                }

                // 3. Direct binary upload to signed storage URL
                item.status = 'uploading';

                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    item.xhr = xhr;
                    xhr.open('PUT', presignedUrl, true);
                    xhr.setRequestHeader('Content-Type', item.file.type || 'image/jpeg');

                    if (extraHeaders) {
                        for (const [k, v] of Object.entries(extraHeaders)) {
                            try {
                                xhr.setRequestHeader(k, v);
                            } catch (_) {}
                        }
                    }

                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            item.progress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve();
                        } else {
                            reject(new Error(xhr.status === 413 ? 'Photo exceeds maximum allowed size (50MB)' : 'Upload could not be completed'));
                        }
                    };

                    xhr.onerror = () => reject(new Error('Network error during upload. Please retry.'));
                    xhr.onabort = () => reject(new Error('Cancelled'));
                    xhr.send(item.file);
                });

                if (item.status === 'cancelled') {
                    this.abortSession(sessionId);
                    return;
                }

                // 4. Confirm photo upload
                item.status = 'processing';

                const confirmRes = await fetch(this.confirmUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        upload_session_id: sessionId,
                        session_id: sessionId
                    })
                });

                if (!confirmRes.ok) {
                    const errData = await confirmRes.json().catch(() => ({}));
                    throw new Error(errData.message || 'Photo confirmation could not be completed');
                }

                item.status = 'done';
                item.progress = 100;
                item.errorMessage = '';
            } catch (err) {
                if (item.status === 'cancelled') return;

                item.status = 'error';
                item.errorMessage = cleanErrorMessage(err.message);

                if (sessionId) {
                    this.abortSession(sessionId);
                }
            } finally {
                item.xhr = null;
                this.runQueue();
                this.checkAllCompleted();
            }
        },

        abortSession(sessionId) {
            if (!sessionId) return;
            fetch(this.abortUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    upload_session_id: sessionId,
                    session_id: sessionId,
                    reason: 'Cancelled'
                })
            }).catch(() => {});
        },

        cancelUpload(id) {
            const item = this.uploads.find(u => u.id === id);
            if (!item) return;

            item.status = 'cancelled';
            if (item.xhr) {
                try {
                    item.xhr.abort();
                } catch (_) {}
            }
            if (item.sessionId) {
                this.abortSession(item.sessionId);
            }

            this.runQueue();
            this.checkAllCompleted();
        },

        retryUpload(id) {
            const item = this.uploads.find(u => u.id === id);
            if (!item) return;

            item.status = 'queued';
            item.progress = 0;
            item.errorMessage = '';
            this.statusSummary = null;
            this.runQueue();
        },

        retryAllFailed() {
            this.uploads.forEach(item => {
                if (item.status === 'error' || item.status === 'cancelled') {
                    item.status = 'queued';
                    item.progress = 0;
                    item.errorMessage = '';
                }
            });
            this.statusSummary = null;
            this.runQueue();
        },

        checkAllCompleted() {
            const stillActive = this.uploads.some(u => u.status === 'queued' || u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing');
            if (stillActive) return;

            const completed = this.uploads.filter(u => u.status === 'done').length;
            const failed = this.uploads.filter(u => u.status === 'error').length;

            if (completed > 0 && failed === 0) {
                this.statusSummary = {
                    type: 'success',
                    message: `All ${completed} photos uploaded successfully! Updating gallery...`
                };
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else if (completed > 0 && failed > 0) {
                this.statusSummary = {
                    type: 'warning',
                    message: `${completed} photo${completed > 1 ? 's' : ''} uploaded, ${failed} failed. You can retry the failed photos or refresh now.`
                };
            } else if (failed > 0) {
                this.statusSummary = {
                    type: 'error',
                    message: `Upload could not be completed for ${failed} photo${failed > 1 ? 's' : ''}. Please retry.`
                };
            }
        },

        refreshGallery() {
            window.location.reload();
        }
    };
};

if (window.Alpine) {
    Alpine.data('galleryUploader', window.galleryUploader);
}
</script>
@endsection
