{{--
    Full-screen preview of the real client gallery. Open with $dispatch('open-gallery-preview'). Unsaved cover
    settings are read from the form #{{ $formId }} and passed to the studio preview route, which renders
    public/gallery without recording anything.
--}}
<div x-data="{
        open: false,
        show() {
            const form = document.getElementById(@js($formId));
            const field = name => form?.querySelector(`[name='${name}']`)?.value || '';
            const url = new URL(@js($previewUrl), location.origin);
            if (field('cover_photo_uuid')) url.searchParams.set('cover', field('cover_photo_uuid'));
            if (field('cover_focal_x') !== '') url.searchParams.set('focal', `${field('cover_focal_x')},${field('cover_focal_y')}`);
            this.open = true;
            this.$nextTick(() => $dispatch('gallery-preview-load', { target: 'sheet', url: url.toString() }));
        },
     }"
     x-show="open"
     x-cloak
     @open-gallery-preview.window="show()"
     @keydown.escape.window="open = false"
     x-trap.noscroll="open"
     class="fixed inset-0 z-[90] flex flex-col bg-neutral-950 text-white"
     role="dialog" aria-modal="true" aria-labelledby="gallery-preview-title">
    <div class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-3 sm:px-5">
        <h2 id="gallery-preview-title" class="truncate text-sm font-medium">What your client sees</h2>
        <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                @click="open = false" aria-label="Close preview">
            <x-icon name="x" />
        </button>
    </div>
    <div class="min-h-0 flex-1 px-3 pb-3 pt-3 sm:px-5">
        @include('studio.galleries.partials.preview-device', ['name' => 'sheet', 'previewUrl' => $previewUrl, 'tone' => 'dark', 'autoload' => false])
    </div>
</div>
