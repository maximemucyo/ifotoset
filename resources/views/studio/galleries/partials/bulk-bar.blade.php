{{-- Selection action bar: cover, move to set, hide, show and delete for the selected photos. --}}
@php
    $bbBtn = 'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium text-background/85 transition-colors hover:bg-background/10 hover:text-background focus-visible:outline-2 focus-visible:outline-background disabled:pointer-events-none disabled:opacity-40 [&_svg]:size-4';
@endphp
<div x-show="selectMode || selectedCount > 0"
     x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-3"
     class="fixed inset-x-3 bottom-4 z-40 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 lg:left-[calc(50%+var(--gw-rail,0px)/2)]"
     role="toolbar" aria-label="Selection actions">
    <div class="flex items-center gap-0.5 rounded-xl bg-foreground p-1.5 text-background shadow-xl">
        <button type="button" class="{{ $bbBtn }} !px-2" @click="clearSelection()" aria-label="Clear selection" title="Clear selection (Esc)">
            <x-icon name="x" />
        </button>

        <div x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape.stop="close()">
            <button type="button" class="{{ $bbBtn }} tabular-nums" @click="toggle()" :aria-expanded="open" aria-haspopup="menu">
                <span x-text="selectedCount === 0 ? 'Select photos' : selectedCount + ' selected'">Select photos</span>
                <x-icon name="loader-circle" class="animate-spin" x-show="selectingAll" x-cloak />
                <x-icon name="chevron-down" x-show="!selectingAll" />
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="menu absolute bottom-full left-0 mb-2 w-48" role="menu">
                <button type="button" class="menu-item" role="menuitem" @click="close(); selectAll()">
                    Select all <span class="ml-auto tabular-nums text-muted-foreground" x-text="viewCount"></span>
                </button>
                <button type="button" class="menu-item" role="menuitem" @click="close(); setSelection([])">Select none</button>
            </div>
        </div>

        <div class="mx-1 h-5 w-px shrink-0 bg-background/20" aria-hidden="true"></div>

        {{-- Outside the scrolling strip below, which would clip its menu --}}
        <div x-show="sets.length" x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape.stop="close()">
            <button type="button" class="{{ $bbBtn }}" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" :disabled="bulkBusy || selectedCount === 0" title="Move to set">
                <x-icon name="folder-input" /> <span class="hidden sm:inline">Move to set</span>
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="menu absolute bottom-full left-0 mb-2 max-h-72 w-56 overflow-y-auto" role="menu">
                <template x-for="set in sets" :key="set.uuid">
                    <button type="button" class="menu-item" role="menuitem" @click="close(); moveSelectedToSet(set.uuid)" :disabled="set.uuid === activeSet">
                        <span class="truncate" x-text="set.name"></span>
                    </button>
                </template>
                <div class="menu-separator"></div>
                <button type="button" class="menu-item" role="menuitem" @click="close(); moveSelectedToSet('')">Remove from set</button>
            </div>
        </div>

        <div class="flex min-w-0 items-center gap-0.5 overflow-x-auto">
            <button type="button" class="{{ $bbBtn }}" x-show="selectedCount === 1"
                    @click="setCover(selected[0]); clearSelection()" :disabled="bulkBusy" title="Set as cover">
                <x-icon name="star" /> <span class="hidden sm:inline">Cover</span>
            </button>
            <button type="button" class="{{ $bbBtn }}" @click="bulkSetHidden(true)" :disabled="bulkBusy || selectedCount === 0" title="Hide from the client gallery">
                <x-icon name="eye-off" /> <span class="hidden sm:inline">Hide</span>
            </button>
            <button type="button" class="{{ $bbBtn }}" @click="bulkSetHidden(false)" :disabled="bulkBusy || selectedCount === 0" title="Show in the client gallery">
                <x-icon name="eye" /> <span class="hidden sm:inline">Show</span>
            </button>
            <button type="button" class="{{ $bbBtn }} hover:!bg-destructive hover:!text-white" @click="requestDelete(selected)" :disabled="bulkBusy || selectedCount === 0" title="Move to trash">
                <x-icon name="trash-2" /> <span class="hidden sm:inline">Delete</span>
            </button>
            <span x-show="bulkBusy" x-cloak class="px-2 text-background/70"><x-icon name="loader-circle" class="size-4 animate-spin" /></span>
        </div>
    </div>
</div>
