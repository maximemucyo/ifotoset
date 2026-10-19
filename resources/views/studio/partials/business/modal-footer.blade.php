{{-- Sticky action bar for business modals. Vars: $cancel (Alpine expr), $submit (label), $icon (optional) --}}
<div class="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-end sm:px-6">
    <button type="button" class="btn btn-outline" @click="{{ $cancel }}">Cancel</button>
    <button type="submit" class="btn btn-primary">
        @if(!empty($icon))<x-icon :name="$icon" />@endif
        {{ $submit }}
    </button>
</div>
