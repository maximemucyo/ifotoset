@extends('layouts.app', ['title' => 'Favorites · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@php
    $tabUrl = route('studio.galleries.favorites', $gallery->uuid);
    $exportUrl = route('studio.galleries.favorites.export', ['uuid' => $gallery->uuid] + ($person ? ['person' => $person->email] : []));
    $settingsUrl = route('studio.galleries.edit', $gallery->uuid) . '#favorites';
    $displayName = fn ($list) => $names[$list->email] ?? $list->email;
    // Lightroom's Text filter reads commas as "or"
    $lightroomText = $filenames->implode(',');
    $statusFor = function ($list) {
        if (!$list->submitted_at) {
            return ['text' => 'Still choosing', 'class' => 'text-muted-foreground'];
        }
        $sent = 'Sent ' . $list->submitted_at->timezone(\App\Models\Gallery::expiryTimezone(request()->account()))->format('M j \a\t g:i A');

        return $list->changedSinceSubmitted()
            ? ['text' => $sent . ', changed since', 'class' => 'text-warning']
            : ['text' => $sent, 'class' => 'text-success'];
    };
@endphp

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="favorites">

    <x-slot:rail>
        <nav aria-label="Clients who picked favorites">
            <h2 class="px-3 pb-1 text-[13px] font-medium text-foreground">People</h2>
            <ul>
                <li>
                    <a href="{{ $tabUrl }}" @if(!$person) aria-current="page" @endif
                       @class(['flex items-baseline justify-between gap-3 rounded-lg px-3 py-2 text-[13px] transition-colors',
                               'bg-muted font-medium text-foreground' => !$person,
                               'text-muted-foreground hover:bg-muted/60 hover:text-foreground' => $person])>
                        <span>Everyone</span>
                        <span class="tabular-nums">{{ $everyoneCount }}</span>
                    </a>
                </li>
                @foreach($lists as $list)
                    @php($isActive = $person?->id === $list->id)
                    <li>
                        <a href="{{ $tabUrl }}?person={{ urlencode($list->email) }}" @if($isActive) aria-current="page" @endif
                           title="{{ $list->email }}"
                           @class(['flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-[13px] transition-colors',
                                   'bg-muted font-medium text-foreground' => $isActive,
                                   'text-muted-foreground hover:bg-muted/60 hover:text-foreground' => !$isActive])>
                            <span class="flex min-w-0 items-center gap-1.5">
                                <span class="truncate">{{ $displayName($list) }}</span>
                                @if($list->submitted_at)
                                    <x-icon name="send" class="size-3 shrink-0 {{ $list->changedSinceSubmitted() ? 'text-warning' : 'text-success' }}" />
                                    <span class="sr-only">sent</span>
                                @endif
                            </span>
                            <span class="tabular-nums">{{ $list->favorites_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if($lists->isEmpty())
                <p class="px-3 pt-2 text-[12px] leading-relaxed text-muted-foreground">Each client who taps the heart shows here with their email.</p>
            @endif
        </nav>
    </x-slot:rail>

    <section aria-labelledby="favorites-title">
        {{-- People on phones, where the rail is hidden --}}
        @if($lists->isNotEmpty())
            <div class="-mx-4 mb-4 overflow-x-auto px-4 [scrollbar-width:none] lg:hidden">
                <div class="flex w-max gap-1.5">
                    <a href="{{ $tabUrl }}" @class(['inline-flex h-8 items-center rounded-full px-3.5 text-[13px]', 'bg-foreground text-background' => !$person, 'bg-muted text-muted-foreground' => $person])>Everyone</a>
                    @foreach($lists as $list)
                        <a href="{{ $tabUrl }}?person={{ urlencode($list->email) }}"
                           @class(['inline-flex h-8 max-w-56 items-center gap-1.5 rounded-full px-3.5 text-[13px]', 'bg-foreground text-background' => $person?->id === $list->id, 'bg-muted text-muted-foreground' => $person?->id !== $list->id])>
                            <span class="truncate">{{ $displayName($list) }}</span>
                            <span class="tabular-nums opacity-70">{{ $list->favorites_count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!$gallery->favorites_enabled && $lists->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <h2 id="favorites-title" class="text-sm font-medium text-foreground">Favorites are off</h2>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">Clients can't pick photos in this gallery.</p>
                <a href="{{ $settingsUrl }}" class="btn btn-outline btn-sm mt-4">Turn on favorites</a>
            </div>
        @elseif($lists->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <h2 id="favorites-title" class="text-sm font-medium text-foreground">No favorites yet</h2>
                <p class="mx-auto mt-1 max-w-sm text-[13px] leading-relaxed text-muted-foreground">
                    When a client taps the heart on a photo, it shows here with their email. They can send you their picks when they're done, and you'll get an email.
                </p>
            </div>
        @else
            <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    @if($person)
                        @php($status = $statusFor($person))
                        <h2 id="favorites-title" class="truncate text-lg font-semibold text-foreground">{{ $displayName($person) }}</h2>
                        @if(isset($names[$person->email]))
                            <p class="truncate text-[13px] text-muted-foreground">{{ $person->email }}</p>
                        @endif
                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-[13px]">
                            <span class="tabular-nums text-foreground">{{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}</span>
                            <span class="{{ $status['class'] }}">{{ $status['text'] }}</span>
                        </p>
                    @else
                        <h2 id="favorites-title" class="text-lg font-semibold text-foreground">Everyone's favorites</h2>
                        <p class="mt-0.5 text-[13px] text-muted-foreground">
                            {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }} picked by {{ $lists->count() }} {{ Str::plural('person', $lists->count()) }}
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 gap-2">
                    <a href="{{ $exportUrl }}" class="btn btn-outline btn-sm"><x-icon name="file-spreadsheet" /> CSV</a>
                    <button type="button" class="btn btn-primary btn-sm" @click="$clipboard(@js($lightroomText), @js($filenames->count() . ' file names copied'))" @disabled($filenames->isEmpty())>
                        <x-icon name="copy" /> Copy file names
                    </button>
                </div>
            </header>

            @if($filenames->isNotEmpty())
                {{-- The exact text that goes to Lightroom --}}
                <figure class="mb-6 rounded-lg border border-border bg-muted/40" x-data="{ all: false }">
                    <p class="px-4 pt-3 text-[13px] leading-6 text-foreground tabular-nums break-words" :class="all ? '' : 'line-clamp-3'">{{ $filenames->implode(', ') }}</p>
                    <figcaption class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 pb-3 pt-1 text-[12px] text-muted-foreground">
                        <span>In Lightroom, paste into the Library filter: Text, Filename, Contains.</span>
                        @if($filenames->count() > 12)
                            <button type="button" class="font-medium text-foreground underline-offset-2 hover:underline" @click="all = !all" x-text="all ? 'Show less' : 'Show all {{ $filenames->count() }}'">Show all {{ $filenames->count() }}</button>
                        @endif
                    </figcaption>
                </figure>
            @endif

            @if($photos->isEmpty())
                <p class="text-[13px] text-muted-foreground">{{ $person ? 'They removed all their picks.' : 'No photos picked right now.' }}</p>
            @else
                <ul class="grid grid-cols-3 gap-x-3 gap-y-4 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8" aria-label="Picked photos">
                    @foreach($photos as $photo)
                        <li class="min-w-0">
                            <div class="relative flex aspect-square items-center justify-center bg-muted p-[7%]">
                                <img src="{{ $photo->getThumbnailUrl('sm') ?: $photo->getUrl('sm') }}" alt="{{ $photo->original_filename }}"
                                     loading="lazy" decoding="async" onerror="this.style.visibility='hidden'"
                                     @class(['size-full object-contain', 'opacity-40 grayscale' => $photo->is_hidden]) />
                                <div class="pointer-events-none absolute bottom-1.5 left-1.5 flex gap-1">
                                    @if(!$person && $photo->favorites_count > 1)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-black/65 px-1.5 py-0.5 text-[10px] font-medium text-white" title="Picked by {{ $photo->favorites_count }} people">
                                            <x-icon name="heart" class="size-3 fill-current" /> {{ $photo->favorites_count }}
                                        </span>
                                    @endif
                                    @if($photo->is_hidden)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-black/65 px-1.5 py-0.5 text-[10px] font-medium text-white" title="Hidden from the client gallery">
                                            <x-icon name="eye-off" class="size-3" /> Hidden
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <p class="truncate px-0.5 pt-1.5 text-[11px] text-muted-foreground" title="{{ $photo->original_filename }}">{{ $photo->original_filename }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
    </x-studio.gallery-workspace>
</div>
@endsection
