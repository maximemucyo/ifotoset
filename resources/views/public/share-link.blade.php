{{--
    Quick share link: the photos a photographer chose from a gallery, opened with the link alone.
    A grid and a simple viewer; downloads follow the gallery's own download choice.
--}}
@php
    $tokens = app(\App\Services\MediaTokenService::class);
    $items = $photos->map(fn ($photo) => [
        'uuid' => $photo->uuid,
        'name' => $photo->original_filename,
        'src' => $photo->isVideo() ? ($photo->getPosterUrl('xl') ?? '') : $photo->getUrl('xl'),
        'video' => $photo->isVideo() ? $tokens->getDeliveryUrl($photo, $gallery) : null,
        'download' => $canDownload ? route('share-link.download', [$link->token, $photo->uuid]) : null,
    ])->values();
@endphp

@extends('layouts.public', [
    'seo' => null,
    'hideNav' => true,
    'defaultTheme' => 'light',
])

@push('styles')
<link rel="preconnect" href="https://{{ config('filesystems.disks.b2.cdn_domain') }}">
@endpush
@include('public.partials.gallery-brand')

@section('content')
<div x-data="{
        items: @js($items),
        index: null,
        get current() { return this.index === null ? null : this.items[this.index] },
        open(i) { this.index = i },
        close() { this.index = null },
        step(d) { this.index = (this.index + d + this.items.length) % this.items.length },
     }"
     @keydown.escape.window="close()"
     @keydown.arrow-right.window="index !== null && step(1)"
     @keydown.arrow-left.window="index !== null && step(-1)">

    <header class="px-4 pb-8 pt-12 sm:px-6 sm:pt-16 lg:px-10">
        @if($gallery->brand() && $photographer->logo_url)
            <x-gallery.logo :photographer="$photographer" class="mb-6 h-8 max-w-[200px]" />
        @else
            <p class="text-sm text-muted-foreground">{{ $photographer->name }}</p>
        @endif
        <h1 class="mt-2 font-serif text-4xl leading-[1.05] tracking-normal text-foreground sm:text-5xl">{{ $gallery->title }}</h1>
        <p class="mt-3 text-sm text-muted-foreground">
            {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }} shared with you
        </p>
    </header>

    @if($photos->isEmpty())
        <p class="px-4 pb-24 text-sm text-muted-foreground sm:px-6 lg:px-10">These photos are no longer shared.</p>
    @else
        <ul class="columns-2 gap-1 px-1 pb-16 sm:columns-3 sm:gap-3 sm:px-6 lg:columns-4 lg:px-10" aria-label="Photos">
            @foreach($photos as $i => $photo)
                <li class="mb-1 break-inside-avoid sm:mb-3">
                    <button type="button" class="group relative block w-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            @click="open({{ $i }})" aria-label="Open {{ $photo->original_filename }}">
                        <img src="{{ $photo->getThumbnailUrl('md') }}" srcset="{{ $photo->getSrcset() }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                             alt="{{ $photo->original_filename }}" loading="{{ $i < 8 ? 'eager' : 'lazy' }}" decoding="async"
                             @if($photo->width && $photo->height) width="{{ $photo->width }}" height="{{ $photo->height }}" @endif
                             class="h-auto w-full bg-muted">
                        @if($photo->isVideo())
                            <span class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-md bg-black/65 px-1.5 py-0.5 text-[11px] font-medium text-white">
                                <x-icon name="play" class="size-3 fill-current" /> {{ $photo->duration_formatted ?: 'Video' }}
                            </span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Viewer --}}
    <div x-show="current" x-cloak x-trap.noscroll="current" class="fixed inset-0 z-50 flex flex-col bg-black text-white" role="dialog" aria-modal="true" :aria-label="current?.name">
        <div class="flex h-14 shrink-0 items-center justify-between gap-3 px-3 sm:px-5">
            <p class="min-w-0 truncate text-[13px] text-white/70">
                <span class="tabular-nums" x-text="`${index + 1} of ${items.length}`"></span>
            </p>
            <div class="flex items-center gap-1">
                <a x-show="current?.download" :href="current?.download" class="inline-flex h-9 items-center gap-2 rounded-full px-3 text-[13px] font-medium text-white/85 hover:bg-white/10 hover:text-white">
                    <x-icon name="download" class="size-4" /> Download
                </a>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-full text-white/85 hover:bg-white/10 hover:text-white" @click="close()" aria-label="Close">
                    <x-icon name="x" class="size-5" />
                </button>
            </div>
        </div>
        <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-4 sm:px-16"
             x-data="{ startX: null }"
             @touchstart.passive="startX = $event.touches[0].clientX"
             @touchend="if (startX !== null) { const dx = $event.changedTouches[0].clientX - startX; if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1); startX = null }">
            <template x-if="current && !current.video">
                <img :src="current.src" :alt="current.name" class="max-h-full max-w-full object-contain">
            </template>
            <template x-if="current && current.video">
                <video :src="current.video" :poster="current.src" controls playsinline class="max-h-full max-w-full"></video>
            </template>
            <template x-if="items.length > 1">
                <div>
                    <button type="button" class="absolute left-2 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex" @click="step(-1)" aria-label="Previous photo">
                        <x-icon name="chevron-left" class="size-6" />
                    </button>
                    <button type="button" class="absolute right-2 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex" @click="step(1)" aria-label="Next photo">
                        <x-icon name="chevron-right" class="size-6" />
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@section('footer')
    @include('public.partials.gallery-footer')
@endsection
