{{--
    Layout, photo grid and colours of the client gallery, on the Design tab under Cover. Expects $gallery and
    $previewUrl. Each choice saves as it changes; the docked preview then reloads. Picking a layout also picks
    its usual grid, which the photographer can change after.
--}}
@php
    $design = $gallery->design();
    $templates = \App\Models\Gallery::TEMPLATES;
    $photo = 'bg-muted-foreground/35';
    $line = 'h-[3px] rounded-full bg-foreground/60';
@endphp
<form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" class="card mt-4 p-5 sm:p-6"
      x-data="settingsForm" @submit.prevent>
    @csrf
    @method('PATCH')
    <div x-data="{
        template: @js($design['template']),
        grid: @js($design['grid']),
        mode: @js($design['mode']),
        grids: @js(collect($templates)->map(fn ($t) => $t['grid'])),
        async change(message) {
            await this.$nextTick();
            if (await this.save(message)) $dispatch('gallery-preview-load', { target: 'docked', url: @js($previewUrl) });
        },
        pickTemplate(key) {
            if (this.template === key) return;
            this.template = key;
            this.grid = this.grids[key];
            this.change('Layout saved');
        },
      }">
    <input type="hidden" name="template" :value="template" value="{{ $design['template'] }}">
    <input type="hidden" name="grid_style" :value="grid" value="{{ $design['grid'] }}">
    <input type="hidden" name="color_mode" :value="mode" value="{{ $design['mode'] }}">

    <fieldset>
        <legend class="text-[15px] font-semibold text-foreground">Layout</legend>
        <p class="mt-1 text-[13px] text-muted-foreground">How the top of the gallery looks.</p>
        <div class="mt-4 grid grid-cols-2 gap-3" role="radiogroup" aria-label="Layout">
            @foreach($templates as $key => $t)
                <button type="button" role="radio" :aria-checked="template === @js($key)" @click="pickTemplate(@js($key))"
                        class="rounded-lg border p-2 text-left outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring"
                        :class="template === @js($key) ? 'border-foreground' : 'border-border hover:border-border-strong'">
                    {{-- A small drawing of the layout --}}
                    <span class="flex aspect-[16/10] w-full flex-col overflow-hidden rounded-[3px] bg-background ring-1 ring-border" aria-hidden="true">
                        @switch($key)
                            @case('full')
                                <span class="relative flex flex-1 flex-col items-center justify-end gap-1 pb-3 {{ $photo }}">
                                    <span class="h-[4px] w-1/2 rounded-full bg-white/90"></span>
                                    <span class="h-[3px] w-1/4 rounded-full bg-white/70"></span>
                                </span>
                                @break
                            @case('framed')
                                <span class="mx-3 mt-2 flex-1 {{ $photo }}"></span>
                                <span class="flex flex-col items-center gap-1 py-2">
                                    <span class="{{ $line }} w-1/2"></span>
                                    <span class="h-[3px] w-1/4 rounded-full bg-foreground/30"></span>
                                </span>
                                @break
                            @case('split')
                                <span class="flex flex-1">
                                    <span class="flex w-1/2 flex-col justify-center gap-1 px-2">
                                        <span class="{{ $line }} w-4/5"></span>
                                        <span class="h-[3px] w-1/2 rounded-full bg-foreground/30"></span>
                                    </span>
                                    <span class="w-1/2 {{ $photo }}"></span>
                                </span>
                                @break
                            @default
                                <span class="flex flex-col gap-1 px-2 pb-1.5 pt-2">
                                    <span class="{{ $line }} w-1/2"></span>
                                    <span class="h-[3px] w-1/4 rounded-full bg-foreground/30"></span>
                                </span>
                                <span class="grid flex-1 grid-cols-4 gap-px px-2 pb-2">
                                    @for($i = 0; $i < 8; $i++)<span class="{{ $photo }}"></span>@endfor
                                </span>
                        @endswitch
                    </span>
                    <span class="mt-2 block text-[13px] font-medium text-foreground">{{ $t['name'] }}</span>
                    <span class="block text-xs leading-snug text-muted-foreground">{{ $t['description'] }}</span>
                </button>
            @endforeach
        </div>
        <p x-show="template === 'none'" x-cloak class="mt-3 text-[13px] text-muted-foreground">The cover photo isn't shown with this layout.</p>
    </fieldset>

    <fieldset class="mt-6 border-t border-border pt-5">
        <legend class="text-[13px] font-medium text-foreground">Photo grid</legend>
        <div class="mt-2 flex gap-1 rounded-lg bg-muted p-1" role="radiogroup" aria-label="Photo grid">
            @foreach(\App\Models\Gallery::GRID_STYLES as $key => $label)
                <button type="button" role="radio" :aria-checked="grid === @js($key)"
                        @click="if (grid !== @js($key)) { grid = @js($key); change('Grid saved') }"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md px-2 py-1.5 text-[13px] transition-colors"
                        :class="grid === @js($key) ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    <span aria-hidden="true" @class([
                        'grid size-4 shrink-0 gap-px',
                        'grid-cols-2 grid-rows-[3fr_2fr]' => $key === 'masonry',
                        'grid-cols-[3fr_2fr] grid-rows-2' => $key === 'rows',
                        'grid-cols-2 grid-rows-2' => $key === 'square',
                    ])>
                        @for($i = 0; $i < 4; $i++)<span class="rounded-[1px] bg-current opacity-60"></span>@endfor
                    </span>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="mt-5">
        <legend class="text-[13px] font-medium text-foreground">Colours</legend>
        <div class="mt-2 flex gap-1 rounded-lg bg-muted p-1" role="radiogroup" aria-label="Colours">
            @foreach(\App\Models\Gallery::COLOR_MODES as $key => $mode)
                <button type="button" role="radio" :aria-checked="mode === @js($key)"
                        @click="if (mode !== @js($key)) { mode = @js($key); change('Colours saved') }"
                        class="flex-1 rounded-md px-2 py-1.5 text-[13px] transition-colors"
                        :class="mode === @js($key) ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    {{ $mode['label'] }}
                </button>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted-foreground">Clients can still switch between light and dark.</p>
    </fieldset>
    </div>
</form>
