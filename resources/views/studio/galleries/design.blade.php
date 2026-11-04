@extends('layouts.app', ['title' => 'Design · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@php
    $previewUrl = route('studio.galleries.preview', $gallery->uuid);
    $coverPhoto = $gallery->coverPhoto && ! $gallery->coverPhoto->is_hidden ? $gallery->coverPhoto : null;
    $coverConfig = $coverPhoto ? [
        'uuid' => $coverPhoto->uuid,
        'url' => $coverPhoto->isVideo() ? ($coverPhoto->getPosterUrl('lg') ?? $coverPhoto->getUrl('lg')) : $coverPhoto->getUrl('lg'),
        'width' => $coverPhoto->width ?: 3,
        'height' => $coverPhoto->height ?: 2,
        'x' => (float) ($gallery->cover_focal_x ?? 0.5),
        'y' => (float) ($gallery->cover_focal_y ?? 0.5),
        'photosUrl' => route('studio.galleries.photos', ['uuid' => $gallery->uuid, 'per_page' => 60]),
    ] : null;
@endphp

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="design">

    {{-- Wide screens keep the real client gallery beside the controls; smaller ones open it with Preview --}}
    <div class="xl:grid xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)] xl:items-start xl:gap-8">
        <div class="min-w-0">
            <header class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Cover</h2>
                    <p class="mt-1 text-[13px] text-muted-foreground">The first thing your client sees. Click the photo where the subject is, so phones and computers both keep it in view.</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm shrink-0 xl:hidden" @click="$dispatch('open-gallery-preview')"><x-icon name="eye" /> Preview</button>
            </header>

            @if($coverConfig)
                <form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" id="gallery-design-form"
                      x-data="settingsForm" @submit.prevent>
                    @csrf
                    @method('PATCH')
                    @include('studio.galleries.partials.cover-editor', ['cover' => $coverConfig])
                </form>
            @else
                <div class="card p-5 text-[13px] text-muted-foreground sm:p-6">
                    The first photo you add becomes the cover. Come back here to choose another one and place its focal point.
                    <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="mt-3 block font-medium text-foreground underline underline-offset-2">Add photos</a>
                </div>
            @endif

            @include('studio.galleries.partials.layout-picker', ['gallery' => $gallery, 'previewUrl' => $previewUrl])
        </div>

        <aside class="sticky top-0 hidden h-[calc(100dvh-7rem)] xl:block" aria-label="Preview of the client gallery">
            <div class="flex h-full flex-col rounded-xl border border-border bg-muted/40 p-3">
                <div class="flex items-center justify-between gap-2 px-1 pb-2">
                    <h2 class="text-[13px] font-medium text-foreground">What your client sees</h2>
                    <button type="button" class="btn btn-ghost btn-xs" @click="$dispatch('open-gallery-preview')">Full screen</button>
                </div>
                <div class="min-h-0 flex-1">
                    @include('studio.galleries.partials.preview-device', [
                        'name' => 'docked',
                        'previewUrl' => $previewUrl,
                        'tone' => 'light',
                        'autoload' => true,
                    ])
                </div>
            </div>
        </aside>
    </div>

    @include('studio.galleries.partials.preview-sheet', ['formId' => 'gallery-design-form', 'previewUrl' => $previewUrl])
    </x-studio.gallery-workspace>
</div>
@endsection
