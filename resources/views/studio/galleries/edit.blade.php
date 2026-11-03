@extends('layouts.app', ['title' => 'Settings · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="settings">

    {{-- Wide screens: the real client gallery stays beside the form; smaller ones use the Preview button --}}
    <div class="xl:grid xl:grid-cols-[minmax(320px,380px)_minmax(0,1fr)] xl:items-start xl:gap-8">
    <aside class="sticky top-6 hidden h-[calc(100dvh-7rem)] xl:block" aria-label="Preview of the client gallery">
        <div class="flex h-full flex-col rounded-xl border border-border bg-muted/40 p-3">
            <div class="flex items-center justify-between gap-2 px-1 pb-2">
                <h2 class="text-[13px] font-medium text-foreground">What your client sees</h2>
                <button type="button" class="btn btn-ghost btn-xs" @click="$dispatch('open-gallery-preview')">Full screen</button>
            </div>
            <div class="min-h-0 flex-1">
                @include('studio.galleries.partials.preview-device', [
                    'name' => 'docked',
                    'previewUrl' => route('studio.galleries.preview', $gallery->uuid),
                    'tone' => 'light',
                    'autoload' => true,
                ])
            </div>
        </div>
    </aside>

    <form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" enctype="multipart/form-data" class="min-w-0" id="gallery-settings-form"
          x-data="{ visibility: '{{ old('visibility', $gallery->visibility) }}', submitting: false }" @submit="submitting = true">
        @csrf
        @method('PATCH')

        @include('studio.partials.gallery-form', ['gallery' => $gallery])

        <div class="divider my-6"></div>

        {{-- Danger zone --}}
        <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
            <div class="lg:pt-5 xl:pt-0">
                <h2 class="text-[15px] font-semibold text-foreground">Danger zone</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">Trashed galleries can be restored from Trash.</p>
            </div>
            <div class="card flex flex-col gap-4 border-destructive/25 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p class="text-sm font-medium text-foreground">Move gallery to trash</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Clients immediately lose access to this gallery.</p>
                </div>
                <button type="button"
                        onclick="if(confirm('Are you sure you want to move this gallery to trash?')) document.getElementById('delete-gallery-form').submit();"
                        class="btn btn-danger btn-sm shrink-0">
                    <x-icon name="trash-2" /> Move to trash
                </button>
            </div>
        </section>

        <div class="sticky bottom-3 z-20 mt-8 rounded-xl border border-border bg-card/90 px-3 py-2.5 shadow-lg backdrop-blur-md sm:bottom-4 sm:px-4">
            <div class="flex items-center justify-between gap-3">
                <p class="hidden text-[13px] text-muted-foreground sm:block">Last updated {{ $gallery->updated_at->diffForHumans() }}</p>
                <div class="flex flex-1 items-center justify-end gap-2">
                    <button type="button" class="btn btn-outline xl:hidden" @click="$dispatch('open-gallery-preview')"><x-icon name="eye" /> Preview</button>
                    <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                        Save changes
                    </button>
                </div>
            </div>
        </div>
    </form>
    </div>

    @include('studio.galleries.partials.preview-sheet', [
        'formId' => 'gallery-settings-form',
        'previewUrl' => route('studio.galleries.preview', $gallery->uuid),
    ])

    <form id="delete-gallery-form" method="POST" action="{{ route('studio.galleries.destroy', $gallery->uuid) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
    </x-studio.gallery-workspace>
</div>
@endsection
