{{--
    Cover photo and focal point for the gallery settings form. Expects $cover: uuid, url, width, height, x, y
    (focal point, 0..1) and photosUrl (studio photos JSON for the picker). Posts cover_photo_uuid, cover_focal_x
    and cover_focal_y with the form; the crops beside the photo use the same object-position as the client's hero.
--}}
<div class="card p-5 sm:p-6"
     x-data="{
        cover: @js($cover),
        x: @js($cover['x']),
        y: @js($cover['y']),
        changedCover: false,
        dragging: false,
        photos: [],
        nextCursor: null,
        loadingPhotos: false,
        get position() { return `${(this.x * 100).toFixed(1)}% ${(this.y * 100).toFixed(1)}%` },
        place(event) {
            const box = this.$refs.picker.getBoundingClientRect();
            this.x = Math.min(1, Math.max(0, (event.clientX - box.left) / box.width));
            this.y = Math.min(1, Math.max(0, (event.clientY - box.top) / box.height));
        },
        nudge(dx, dy) {
            this.x = Math.min(1, Math.max(0, this.x + dx));
            this.y = Math.min(1, Math.max(0, this.y + dy));
        },
        async openPicker() {
            $dispatch('open-modal', 'cover-picker');
            if (!this.photos.length) await this.loadPhotos();
        },
        async loadPhotos() {
            this.loadingPhotos = true;
            try {
                const url = new URL(this.cover.photosUrl, location.origin);
                if (this.nextCursor) url.searchParams.set('cursor', this.nextCursor);
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                this.photos.push(...json.data.filter(p => !p.is_hidden && p.status !== 'failed'));
                this.nextCursor = json.has_more ? json.next_cursor : null;
            } finally {
                this.loadingPhotos = false;
            }
        },
        choose(photo) {
            this.cover = { ...this.cover, uuid: photo.uuid, url: photo.large_url, width: photo.width || 3, height: photo.height || 2 };
            this.x = 0.5;
            this.y = 0.5;
            this.changedCover = true;
            $dispatch('close-modal', 'cover-picker');
        },
     }">
    <input type="hidden" name="cover_photo_uuid" :value="changedCover ? cover.uuid : ''">
    <input type="hidden" name="cover_focal_x" :value="x.toFixed(3)" value="{{ number_format($cover['x'], 3, '.', '') }}">
    <input type="hidden" name="cover_focal_y" :value="y.toFixed(3)" value="{{ number_format($cover['y'], 3, '.', '') }}">

    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_auto]">
        {{-- The whole photo, uncropped: click or drag to place the focal point; arrow keys nudge it --}}
        <div>
            <div x-ref="picker"
                 role="slider" tabindex="0"
                 aria-label="Cover focal point"
                 :aria-valuetext="`${Math.round(x * 100)}% from the left, ${Math.round(y * 100)}% from the top`"
                 class="relative mx-auto max-h-[420px] cursor-crosshair touch-none select-none bg-muted outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                 :style="`aspect-ratio: ${cover.width} / ${cover.height}; max-width: calc(420px * ${cover.width} / ${cover.height});`"
                 style="aspect-ratio: {{ $cover['width'] }} / {{ $cover['height'] }}; max-width: calc(420px * {{ $cover['width'] }} / {{ $cover['height'] }});"
                 @pointerdown="dragging = true; $el.setPointerCapture($event.pointerId); place($event)"
                 @pointermove="dragging && place($event)"
                 @pointerup="dragging = false"
                 @pointercancel="dragging = false"
                 @keydown.arrow-left.prevent="nudge(-0.02, 0)"
                 @keydown.arrow-right.prevent="nudge(0.02, 0)"
                 @keydown.arrow-up.prevent="nudge(0, -0.02)"
                 @keydown.arrow-down.prevent="nudge(0, 0.02)">
                <img :src="cover.url" src="{{ $cover['url'] }}" alt="Gallery cover" draggable="false" class="pointer-events-none size-full object-contain"
                     @load="if ($el.naturalWidth) { cover.width = $el.naturalWidth; cover.height = $el.naturalHeight }">
                <span class="pointer-events-none absolute size-9 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0/0.35),0_1px_8px_rgb(0_0_0/0.35)]"
                      :style="`left: ${x * 100}%; top: ${y * 100}%`"
                      style="left: {{ $cover['x'] * 100 }}%; top: {{ $cover['y'] * 100 }}%">
                    <span class="absolute left-1/2 top-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"></span>
                </span>
            </div>
        </div>

        {{-- What the client's cover shows on each screen --}}
        <div class="flex items-end justify-center gap-4 sm:flex-col sm:items-center sm:justify-start">
            <figure class="text-center">
                <div class="w-[78px] overflow-hidden rounded-[12px] bg-black ring-1 ring-border" style="aspect-ratio: 390 / 844;">
                    <img :src="cover.url" src="{{ $cover['url'] }}" alt="" class="size-full object-cover" :style="`object-position: ${position}`">
                </div>
                <figcaption class="mt-1.5 text-[11px] text-muted-foreground">Phone</figcaption>
            </figure>
            <figure class="text-center">
                <div class="w-[156px] overflow-hidden rounded-md bg-black ring-1 ring-border" style="aspect-ratio: 1440 / 900;">
                    <img :src="cover.url" src="{{ $cover['url'] }}" alt="" class="size-full object-cover" :style="`object-position: ${position}`">
                </div>
                <figcaption class="mt-1.5 text-[11px] text-muted-foreground">Computer</figcaption>
            </figure>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-outline btn-sm" @click="openPicker()">Change cover</button>
        <button type="button" class="btn btn-ghost btn-sm" x-show="x !== 0.5 || y !== 0.5" x-cloak @click="x = 0.5; y = 0.5">Centre</button>
    </div>
    @error('cover_photo_uuid') <p class="error-text">{{ $message }}</p> @enderror

    <x-ui.modal name="cover-picker" title="Choose a cover" description="Hidden photos can't be the cover." max-width="3xl">
        <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-5">
            <template x-for="photo in photos" :key="photo.uuid">
                <button type="button" @click="choose(photo)"
                        class="relative aspect-square overflow-hidden bg-muted outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :class="photo.uuid === cover.uuid && 'ring-2 ring-foreground ring-offset-2 ring-offset-popover'"
                        :aria-label="`Use ${photo.original_filename || 'this photo'} as the cover`">
                    <img :src="photo.thumbnail_url" alt="" loading="lazy" class="size-full object-cover">
                </button>
            </template>
        </div>
        <p x-show="loadingPhotos" class="mt-4 text-center text-[13px] text-muted-foreground">Loading photos…</p>
        <div x-show="nextCursor && !loadingPhotos" x-cloak class="mt-4 text-center">
            <button type="button" class="btn btn-outline btn-sm" @click="loadPhotos()">Show more</button>
        </div>
    </x-ui.modal>
</div>
