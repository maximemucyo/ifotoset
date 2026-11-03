{{--
    The real client gallery in a phone or computer frame, scaled to fit its container. Used docked beside
    the Settings form and in the full-screen preview sheet. Expects $name (unique per page), $previewUrl,
    $tone (light|dark) and $autoload (load the saved state right away).

    Listens for:
      gallery-preview-load  { target, url }    load the page (with unsaved settings in the query)
      gallery-preview-cover { url, position }  swap the cover in place, keeping the scroll position
--}}
@php($dark = ($tone ?? 'light') === 'dark')
<div class="flex h-full min-h-0 flex-col"
     x-data="{
        device: (() => { try { return localStorage.getItem('studio.preview.device') || 'phone' } catch (e) { return 'phone' } })(),
        devices: { phone: { w: 390, h: 844 }, desktop: { w: 1440, h: 900 } },
        src: @js($autoload ?? false ? $previewUrl : ''),
        loading: @js($autoload ?? false),
        scale: 0.5,
        // A computer screen keeps its width but runs down the whole panel, so photos show under the cover
        desktopHeight: 900,
        get size() { return this.device === 'desktop' ? { w: 1440, h: this.desktopHeight } : this.devices.phone },
        init() {
            // The stage has no size until it is shown, so measure whenever it changes
            new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
        },
        fit() {
            const stage = this.$refs.stage;
            if (!stage?.clientWidth || !stage.clientHeight) return;
            const width = stage.clientWidth - 24, height = stage.clientHeight - 24;
            if (this.device === 'desktop') {
                const byWidth = Math.min(1, width / 1440);
                // Wider than tall enough for one screen: show exactly one screen, scaled to the height
                if (height / byWidth >= 900) {
                    this.scale = byWidth;
                    this.desktopHeight = Math.round(height / byWidth);
                } else {
                    this.scale = Math.max(0.1, height / 900);
                    this.desktopHeight = 900;
                }
            } else {
                this.scale = Math.max(0.1, Math.min(1, width / 390, height / 844));
            }
            this.pinCover();
        },
        // The cover fills one real screen, not the whole extended frame
        pinCover() {
            this.doc?.documentElement.style.setProperty('--preview-cover-height', this.device === 'desktop' ? '900px' : '844px');
        },
        setDevice(d) {
            try { localStorage.setItem('studio.preview.device', d) } catch (e) {}
            // The docked and full-screen previews show the same screen size
            this.$dispatch('gallery-preview-device', d);
        },
        load(url) {
            this.loading = true;
            // Clearing first makes a repeat of the same URL reload too
            this.src = '';
            this.$nextTick(() => this.src = url);
        },
        get doc() {
            try { return this.$refs.frame.contentDocument } catch (e) { return null }
        },
        applyCover({ url, position }) {
            const img = this.doc?.querySelector('#gallery-hero .gallery-hero-img');
            if (!img) return;
            img.style.objectPosition = position;
            if (url && img.getAttribute('src') !== url) {
                img.removeAttribute('srcset');
                img.src = url;
            }
        },
        jump(to) {
            const win = this.$refs.frame.contentWindow;
            if (to === 'photos') this.doc?.getElementById('gallery-action-bar')?.scrollIntoView({ behavior: 'smooth' });
            else win?.scrollTo({ top: 0, behavior: 'smooth' });
        },
     }"
     @gallery-preview-load.window="$event.detail.target === @js($name) && load($event.detail.url)"
     @gallery-preview-cover.window="applyCover($event.detail)"
     @gallery-preview-device.window="device = $event.detail; $nextTick(() => fit())">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 pb-3">
        <div class="flex items-center gap-0.5 rounded-lg p-0.5 {{ $dark ? 'bg-white/10' : 'bg-muted' }}" role="group" aria-label="Screen size">
            @foreach(['phone' => 'Phone', 'desktop' => 'Computer'] as $key => $label)
                <button type="button" class="rounded-md px-2.5 py-1 text-[13px] transition-colors"
                        :class="device === '{{ $key }}' ? '{{ $dark ? 'bg-white text-black' : 'bg-card text-foreground shadow-xs' }}' : '{{ $dark ? 'text-white/70 hover:text-white' : 'text-muted-foreground hover:text-foreground' }}'"
                        :aria-pressed="device === '{{ $key }}'" @click="setDevice('{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex items-center gap-0.5 rounded-lg p-0.5 {{ $dark ? 'bg-white/10' : 'bg-muted' }}" role="group" aria-label="Scroll the preview">
            <button type="button" class="rounded-md px-2.5 py-1 text-[13px] {{ $dark ? 'text-white/70 hover:text-white' : 'text-muted-foreground hover:text-foreground' }}" @click="jump('cover')">Cover</button>
            <button type="button" class="rounded-md px-2.5 py-1 text-[13px] {{ $dark ? 'text-white/70 hover:text-white' : 'text-muted-foreground hover:text-foreground' }}" @click="jump('photos')">Photos</button>
        </div>
    </div>

    <div x-ref="stage" class="relative flex min-h-0 flex-1 items-start justify-center overflow-hidden">
        <div class="relative overflow-hidden bg-white shadow-xl"
             :class="device === 'phone' ? 'rounded-[24px] ring-[5px] ring-neutral-800' : 'rounded-md ring-1 {{ $dark ? 'ring-white/15' : 'ring-border' }}'"
             :style="`width: ${size.w * scale}px; height: ${size.h * scale}px`">
            <iframe x-ref="frame" :src="src" title="Client gallery preview"
                    class="absolute left-0 top-0 origin-top-left border-0"
                    :style="`width: ${size.w}px; height: ${size.h}px; transform: scale(${scale})`"
                    @load="loading = false; pinCover()"></iframe>
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-neutral-100 text-[13px] text-neutral-500">
                Loading preview…
            </div>
        </div>
    </div>
    <p class="shrink-0 pt-2 text-center text-[11px] {{ $dark ? 'text-white/50' : 'text-muted-foreground' }}">Scroll inside to see the photos. Buttons don't respond in the preview.</p>
</div>
