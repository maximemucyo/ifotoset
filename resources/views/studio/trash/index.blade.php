@extends('layouts.app', ['title' => 'Trash · ifotoset'])

@section('content')
@php
    $fmtBytes = function (int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0; $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
        return ($i === 0 ? (int) $value : number_format($value, $value >= 100 ? 0 : 1)) . ' ' . $units[$i];
    };
@endphp

<div x-data="{ emptyModalOpen: false }">
    <x-ui.page-header title="Trash" description="Deleted galleries and photos are kept for {{ $retentionDays }} days so you can restore them. After that they're permanently removed from cloud storage.">
        @if($totalItems > 0)
            <x-slot:actions>
                <button type="button" @click="emptyModalOpen = true" class="btn btn-outline text-destructive hover:bg-destructive/10 hover:text-destructive">
                    <x-icon name="trash-2" /> Empty trash
                </button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    {{-- Summary --}}
    <div class="card grid grid-cols-3 divide-x divide-border">
        <div class="flex items-center gap-3.5 px-3 py-3.5 sm:p-5">
            <span class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground sm:flex"><x-icon name="trash-2" class="size-4" /></span>
            <div>
                <p class="text-xs text-muted-foreground">Items in trash</p>
                <p class="font-display text-base font-semibold tabular-nums text-foreground sm:text-lg">{{ number_format($totalItems) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3.5 px-3 py-3.5 sm:p-5">
            <span class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground sm:flex"><x-icon name="hard-drive" class="size-4" /></span>
            <div>
                <p class="text-xs text-muted-foreground">Storage held</p>
                <p class="font-display text-base font-semibold tabular-nums text-foreground sm:text-lg">{{ $trashBytes > 0 ? $fmtBytes($trashBytes) : '0 MB' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3.5 px-3 py-3.5 sm:p-5">
            <span class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground sm:flex"><x-icon name="history" class="size-4" /></span>
            <div>
                <p class="text-xs text-muted-foreground">Auto-delete after</p>
                <p class="font-display text-base font-semibold tabular-nums text-foreground sm:text-lg">{{ $retentionDays }} {{ \Illuminate\Support\Str::plural('day', $retentionDays) }}</p>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <nav class="tabs-line mt-8" aria-label="Trash sections">
        <a href="{{ route('studio.trash.index', ['tab' => 'galleries']) }}" class="tab-line" @if($tab === 'galleries') aria-selected="true" aria-current="page" @endif>
            <x-icon name="images" class="size-4" /> Galleries
            <span class="rounded bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{{ $galleries->total() }}</span>
        </a>
        <a href="{{ route('studio.trash.index', ['tab' => 'photos']) }}" class="tab-line" @if($tab === 'photos') aria-selected="true" aria-current="page" @endif>
            <x-icon name="image" class="size-4" /> Photos
            <span class="rounded bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{{ $photos->total() }}</span>
        </a>
    </nav>

    <div class="mt-6">
        @if($tab === 'galleries')
            @if($galleries->isEmpty())
                <x-ui.empty-state icon="images"
                    title="No deleted galleries"
                    description="When you delete a gallery it lands here for {{ $retentionDays }} days, along with its photos, so you can bring it back in one click." />
            @else
                <div class="card overflow-hidden">
                    <ul class="divide-y divide-border">
                        @foreach($galleries as $gallery)
                            @php $purgeMsg = "“{$gallery->title}” and all of its original files and image variants will be permanently deleted from cloud storage. This can't be undone."; @endphp
                            <li class="flex flex-col gap-4 px-4 py-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-center sm:px-5">
                                <div class="flex min-w-0 flex-1 items-center gap-3.5">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground">
                                        <x-icon name="images" class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-foreground" title="{{ $gallery->title }}">{{ $gallery->title }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            <span class="tabular-nums">{{ number_format($gallery->photos_count ?? 0) }}</span> {{ \Illuminate\Support\Str::plural('photo', $gallery->photos_count ?? 0) }}
                                            <span class="text-subtle">·</span>
                                            Deleted <span title="{{ $gallery->deleted_at->format('M j, Y g:i A') }}">{{ $gallery->deleted_at->diffForHumans() }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="sm:w-44">
                                    @include('studio.partials.business.retention', ['deletedAt' => $gallery->deleted_at, 'retentionDays' => $retentionDays])
                                </div>
                                <div class="flex items-center gap-2 sm:justify-end">
                                    <form method="POST" action="{{ route('studio.trash.restore') }}" class="flex-1 sm:flex-none">
                                        @csrf
                                        <input type="hidden" name="type" value="gallery">
                                        <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                        <button type="submit" class="btn btn-outline btn-sm w-full">
                                            <x-icon name="rotate-ccw" /> Restore
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('studio.trash.purge') }}" class="flex-1 sm:flex-none"
                                          @submit.prevent="$dispatch('confirm-action', { form: $el, title: 'Delete gallery forever?', message: @js($purgeMsg), confirm: 'Delete forever' })">
                                        @csrf
                                        <input type="hidden" name="type" value="gallery">
                                        <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                        <button type="submit" class="btn btn-danger-ghost btn-sm w-full">
                                            <x-icon name="trash-2" /> Delete forever
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if($galleries->hasPages())
                    <div class="mt-6">{{ $galleries->appends(['tab' => 'galleries'])->links() }}</div>
                @endif
            @endif
        @else
            @if($photos->isEmpty())
                <x-ui.empty-state icon="image"
                    title="No deleted photos"
                    description="Photos you remove from a gallery wait here for {{ $retentionDays }} days. Restore them any time before they're permanently deleted." />
            @else
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach($photos as $photo)
                        @php $photoName = $photo->original_filename ?: $photo->filename; @endphp
                        <li class="card group flex flex-col overflow-hidden">
                            <div class="relative aspect-[4/3] overflow-hidden bg-muted" x-data="{ failed: false }">
                                <img src="{{ $photo->thumbnail_url }}" alt="{{ $photoName }}"
                                     loading="lazy" decoding="async"
                                     x-show="!failed" x-on:error="failed = true"
                                     class="size-full object-cover opacity-90 grayscale-[35%] transition duration-300 group-hover:opacity-100 group-hover:grayscale-0">
                                <div x-show="failed" x-cloak class="flex size-full items-center justify-center text-subtle">
                                    <x-icon name="image" class="size-6" />
                                </div>
                                <div class="absolute left-2 top-2">
                                    @include('studio.partials.business.retention', ['deletedAt' => $photo->deleted_at, 'retentionDays' => $retentionDays, 'variant' => 'badge'])
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col p-3">
                                <p class="truncate text-[13px] font-medium text-foreground" title="{{ $photoName }}">{{ $photoName }}</p>
                                <p class="mt-0.5 flex min-w-0 items-center gap-1 text-xs text-muted-foreground">
                                    <x-icon name="images" class="size-3 shrink-0" />
                                    <span class="truncate">{{ $photo->gallery?->title ?? '—' }}</span>
                                </p>
                                <p class="mt-0.5 text-[11px] tabular-nums text-subtle">{{ $fmtBytes((int) ($photo->size ?? 0)) }} · {{ $photo->deleted_at->diffForHumans() }}</p>
                                <div class="mt-3 flex items-center gap-1.5">
                                    <form method="POST" action="{{ route('studio.trash.restore') }}" class="flex-1">
                                        @csrf
                                        <input type="hidden" name="type" value="photo">
                                        <input type="hidden" name="uuid" value="{{ $photo->uuid }}">
                                        <button type="submit" class="btn btn-outline btn-xs w-full">
                                            <x-icon name="rotate-ccw" /> Restore
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('studio.trash.purge') }}"
                                          @submit.prevent="$dispatch('confirm-action', { form: $el, title: 'Delete photo forever?', message: 'The original file and all generated image variants will be permanently deleted from cloud storage. This can\'t be undone.', confirm: 'Delete forever' })">
                                        @csrf
                                        <input type="hidden" name="type" value="photo">
                                        <input type="hidden" name="uuid" value="{{ $photo->uuid }}">
                                        <button type="submit" class="btn btn-danger-ghost btn-xs btn-icon size-7" title="Delete forever" aria-label="Delete {{ $photoName }} forever">
                                            <x-icon name="trash-2" />
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($photos->hasPages())
                    <div class="mt-6">{{ $photos->appends(['tab' => 'photos'])->links() }}</div>
                @endif
            @endif
        @endif
    </div>

    {{-- Empty trash confirmation --}}
    @component('studio.partials.business.modal', ['show' => 'emptyModalOpen', 'maxWidth' => 'sm'])
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <x-icon name="triangle-alert" class="size-[18px]" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 class="text-base font-semibold tracking-tight text-foreground">Empty trash?</h3>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                        All {{ $totalItems }} {{ \Illuminate\Support\Str::plural('item', $totalItems) }} in trash, including every gallery, photo and generated variant, will be permanently deleted from cloud storage{{ $trashBytes > 0 ? ', freeing ' . $fmtBytes($trashBytes) : '' }}. This can't be undone.
                    </p>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('studio.trash.empty') }}" x-data="{ busy: false }" @submit="busy = true"
              class="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-5 py-3.5 sm:flex-row sm:justify-end sm:px-6">
            @csrf
            <button type="button" @click="emptyModalOpen = false" class="btn btn-outline">Cancel</button>
            <button type="submit" class="btn btn-danger" :disabled="busy">
                <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                <x-icon name="trash-2" x-show="!busy" />
                Empty trash
            </button>
        </form>
    @endcomponent

    @include('studio.partials.business.confirm-dialog')
</div>
@endsection
