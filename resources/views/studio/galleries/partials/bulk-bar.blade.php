{{-- Selection action bar (uses the existing per-photo hide / cover / delete endpoints). --}}
<div x-show="selectMode || selectedCount > 0"
     x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-3"
     class="fixed inset-x-3 bottom-4 z-40 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 lg:left-[calc(50%+8rem)]"
     role="toolbar" aria-label="Selection actions">
    <div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-border bg-popover p-1.5 text-popover-foreground shadow-xl">
        <div class="flex shrink-0 items-center gap-2 pl-2 pr-1.5">
            <span class="flex h-6 min-w-6 items-center justify-center rounded-md bg-foreground px-1.5 text-xs font-semibold tabular-nums text-background" x-text="selectedCount">0</span>
            <span class="hidden text-[13px] font-medium sm:inline" x-text="selectedCount === 0 ? 'Select items' : 'selected'"></span>
        </div>
        <button type="button" class="btn btn-ghost btn-sm shrink-0" @click="selectAllLoaded()" :disabled="bulkBusy">
            <x-icon name="check-check" /> <span class="hidden sm:inline">Select all</span>
        </button>
        <div class="mx-0.5 h-5 w-px shrink-0 bg-border"></div>
        <button type="button" class="btn btn-ghost btn-sm shrink-0" @click="bulkSetHidden(true)" :disabled="bulkBusy || selectedCount === 0" title="Hide from client gallery">
            <x-icon name="eye-off" /> <span class="hidden sm:inline">Hide</span>
        </button>
        <button type="button" class="btn btn-ghost btn-sm shrink-0" @click="bulkSetHidden(false)" :disabled="bulkBusy || selectedCount === 0" title="Show in client gallery">
            <x-icon name="eye" /> <span class="hidden sm:inline">Show</span>
        </button>
        <button type="button" class="btn btn-ghost btn-sm shrink-0" x-show="selectedCount === 1"
                @click="setCover(selected[0]); clearSelection()" :disabled="bulkBusy" title="Set as cover">
            <x-icon name="star" /> <span class="hidden sm:inline">Cover</span>
        </button>
        <button type="button" class="btn btn-danger-ghost btn-sm shrink-0" @click="requestDelete(selected)" :disabled="bulkBusy || selectedCount === 0" title="Move to trash">
            <x-icon name="trash-2" /> <span class="hidden sm:inline">Delete</span>
        </button>
        <div class="mx-0.5 h-5 w-px shrink-0 bg-border"></div>
        <span x-show="bulkBusy" class="px-1 text-muted-foreground"><x-icon name="loader-circle" class="size-4 animate-spin" /></span>
        <button type="button" class="btn btn-ghost btn-icon btn-sm shrink-0" @click="clearSelection()" aria-label="Clear selection" title="Clear selection (Esc)">
            <x-icon name="x" />
        </button>
    </div>
</div>
