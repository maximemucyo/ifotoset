{{--
    New gallery dialog: three fields, then straight to uploading. Everything else (who can open it, expiry,
    downloads) is decided when the photographer shares it. Open with $dispatch('open-modal', 'new-gallery').
--}}
@php
    $reopen = ($openNewGallery ?? false) || (old('_dialog') === 'new-gallery' && $errors->any());
@endphp
<x-ui.modal name="new-gallery" title="New gallery" description="Add photos next. Clients can't see it until you share it." max-width="md">
    <form method="POST" action="{{ route('studio.galleries.store') }}" class="space-y-4"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="_dialog" value="new-gallery">

        <x-ui.input label="Title" name="title" id="new-gallery-title" required
                    placeholder="e.g. Kevin & Aline"
                    :value="old('_dialog') === 'new-gallery' ? old('title') : ''"
                    :error="old('_dialog') === 'new-gallery' ? $errors->first('title') : null"
                    x-init="$watch('open', v => v && $nextTick(() => $el.focus()))" />

        <x-ui.input label="Date" type="date" name="event_date" id="new-gallery-date"
                    :value="old('_dialog') === 'new-gallery' ? old('event_date') : ''"
                    hint="Leave it empty to use the date the photos were taken."
                    :error="old('_dialog') === 'new-gallery' ? $errors->first('event_date') : null" />

        @include('studio.galleries.partials.client-picker', [
            'id' => 'new-gallery-client',
            'name' => old('_dialog') === 'new-gallery' ? old('client_name') : '',
            'uuid' => old('_dialog') === 'new-gallery' ? old('client_uuid') : '',
        ])

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-ghost" @click="$dispatch('close-modal', 'new-gallery')">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
                <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                Create and add photos
            </button>
        </div>
    </form>
</x-ui.modal>
@if($reopen)
    <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'new-gallery'))"></div>
@endif
