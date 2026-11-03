{{--
    Full-screen preview of the real client gallery, framed as a phone or a computer screen.
    Open with $dispatch('open-gallery-preview'). Unsaved cover settings are read from the form #{{ $formId }}
    and passed to the studio preview route, which renders public/gallery without recording anything.
--}}
<div x-data="{
        open: false,
        device: (() => { try { return localStorage.getItem('studio.preview.device') || 'phone' } catch (e) { return 'phone' } })(),
        devices: { phone: { w: 390, h: 844 }, desktop: { w: 1440, h: 900 } },
        src: '',
        loading: true,
        scale: 1,
        get size() { return this.devices[this.device] },
        show() {
            const form = document.getElementById(@js($formId));
            const field = name => form?.querySelector(`[name='${name}']`)?.value || '';
            const url = new URL(@js($previewUrl), location.origin);
            if (field('cover_photo_uuid')) url.searchParams.set('cover', field('cover_photo_uuid'));
            if (field('cover_focal_x') !== '') url.searchParams.set('focal', `${field('cover_focal_x')},${field('cover_focal_y')}`);
            this.loading = true;
            this.src = url.toString();
            this.open = true;
            this.$nextTick(() => this.fit());
        },
        setDevice(d) {
            this.device = d;
            try { localStorage.setItem('studio.preview.device', d) } catch (e) {}
            this.$nextTick(() => this.fit());
        },
        init() {
            // The stage has no size until the sheet is shown, so measure whenever it changes
            new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
        },
        fit() {
            const stage = this.$refs.stage;
            if (!stage?.clientWidth || !stage.clientHeight) return;
            this.scale = Math.max(0.1, Math.min(1, (stage.clientWidth - 32) / this.size.w, (stage.clientHeight - 32) / this.size.h));
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
        <div class="flex items-center gap-1 rounded-lg bg-white/10 p-0.5" role="group" aria-label="Screen size">
            <button type="button" class="rounded-md px-3 py-1 text-[13px] transition-colors"
                    :class="device === 'phone' ? 'bg-white text-black' : 'text-white/70 hover:text-white'"
                    :aria-pressed="device === 'phone'" @click="setDevice('phone')">Phone</button>
            <button type="button" class="rounded-md px-3 py-1 text-[13px] transition-colors"
                    :class="device === 'desktop' ? 'bg-white text-black' : 'text-white/70 hover:text-white'"
                    :aria-pressed="device === 'desktop'" @click="setDevice('desktop')">Computer</button>
        </div>
        <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                @click="open = false" aria-label="Close preview">
            <x-icon name="x" />
        </button>
    </div>

    <div x-ref="stage" class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden">
        <div class="relative overflow-hidden bg-white shadow-2xl"
             :class="device === 'phone' ? 'rounded-[28px] ring-[6px] ring-neutral-800' : 'rounded-md ring-1 ring-white/15'"
             :style="`width: ${size.w * scale}px; height: ${size.h * scale}px`">
            <iframe :src="open ? src : ''" title="Client gallery preview"
                    class="absolute left-0 top-0 origin-top-left border-0"
                    :style="`width: ${size.w}px; height: ${size.h}px; transform: scale(${scale})`"
                    @load="loading = false"></iframe>
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-neutral-100 text-[13px] text-neutral-500">
                Loading preview…
            </div>
        </div>
    </div>
    <p class="shrink-0 px-4 pb-3 text-center text-[11px] text-white/50">Showing unsaved changes. Buttons don't respond in the preview.</p>
</div>
