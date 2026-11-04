@extends('layouts.app', ['title' => 'Activity · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@php
    $tabUrl = route('studio.galleries.activity', $gallery->uuid);
    $viewUrl = fn (string $v, array $extra = []) => route('studio.galleries.activity', ['uuid' => $gallery->uuid, 'view' => $v] + $extra);
    $views = [
        'favorites' => ['label' => 'Favorites', 'icon' => 'heart'],
        'downloads' => ['label' => 'Downloads', 'icon' => 'download'],
        'links' => ['label' => 'Quick links', 'icon' => 'link'],
    ];
    $tz = \App\Models\Gallery::expiryTimezone(request()->account());
    $when = fn ($at) => $at->copy()->timezone($tz)->format($at->isCurrentYear() ? 'M j, g:i A' : 'M j, Y');
    $names ??= [];
    $displayName = fn ($email) => $names[$email] ?? $email;
    $pill = fn (bool $on) => $on ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:text-foreground';
@endphp

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="activity">

    <x-slot:rail>
        <nav aria-label="Activity">
            <ul>
                @foreach($views as $key => $v)
                    <li>
                        <a href="{{ $viewUrl($key) }}" @if($view === $key) aria-current="page" @endif
                           @class(['flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] transition-colors',
                                   'bg-muted font-medium text-foreground' => $view === $key,
                                   'text-muted-foreground hover:bg-muted/60 hover:text-foreground' => $view !== $key])>
                            <x-icon :name="$v['icon']" class="size-4" />
                            <span class="flex-1">{{ $v['label'] }}</span>
                            <span class="tabular-nums">{{ $counts[$key] ?: '' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </x-slot:rail>

    {{-- Views on phones, where the rail is hidden --}}
    <div class="-mx-4 mb-5 overflow-x-auto px-4 [scrollbar-width:none] lg:hidden">
        <div class="flex w-max gap-1.5">
            @foreach($views as $key => $v)
                <a href="{{ $viewUrl($key) }}" class="inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[13px] {{ $pill($view === $key) }}">
                    {{ $v['label'] }} <span class="tabular-nums opacity-70">{{ $counts[$key] ?: '' }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ================= Favorites ================= --}}
    @if($view === 'favorites')
    @php
        $statusFor = function ($list) use ($when) {
            if (!$list->submitted_at) {
                return ['text' => 'Still choosing', 'class' => 'text-muted-foreground'];
            }
            $sent = 'Sent ' . $when($list->submitted_at);

            return $list->changedSinceSubmitted()
                ? ['text' => $sent . ', changed since', 'class' => 'text-warning']
                : ['text' => $sent, 'class' => 'text-success'];
        };
        $exportUrl = route('studio.galleries.favorites.export', ['uuid' => $gallery->uuid] + ($person ? ['person' => $person->email] : []));
        // Lightroom's Text filter reads commas as "or"
        $lightroomText = $filenames->implode(',');
    @endphp
    <section aria-labelledby="activity-title">
        @if(!$gallery->favorites_enabled && $lists->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <h2 id="activity-title" class="text-sm font-medium text-foreground">Favorites are off</h2>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">Clients can't pick photos in this gallery.</p>
                <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}#favorites" class="btn btn-outline btn-sm mt-4">Turn on favorites</a>
            </div>
        @elseif($lists->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <h2 id="activity-title" class="text-sm font-medium text-foreground">No favorites yet</h2>
                <p class="mx-auto mt-1 max-w-sm text-[13px] leading-relaxed text-muted-foreground">
                    When a client taps the heart on a photo, it shows here with their email. They can send you their picks when they're done, and you'll get an email.
                </p>
            </div>
        @else
            {{-- People --}}
            <div class="-mx-4 mb-5 overflow-x-auto px-4 [scrollbar-width:none] lg:mx-0 lg:overflow-visible lg:px-0">
                <ul class="flex w-max gap-1.5 lg:w-auto lg:flex-wrap" aria-label="People who picked favorites">
                    <li><a href="{{ $viewUrl('favorites') }}" class="inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[13px] {{ $pill(!$person) }}">
                        Everyone <span class="tabular-nums opacity-70">{{ $everyoneCount }}</span>
                    </a></li>
                    @foreach($lists as $list)
                        <li><a href="{{ $viewUrl('favorites', ['person' => $list->email]) }}" title="{{ $list->email }}"
                               class="inline-flex h-8 max-w-64 items-center gap-1.5 rounded-full px-3.5 text-[13px] {{ $pill($person?->id === $list->id) }}">
                            <span class="truncate">{{ $displayName($list->email) }}</span>
                            @if($list->submitted_at)
                                <x-icon name="send" class="size-3 shrink-0" /><span class="sr-only">sent</span>
                            @endif
                            <span class="tabular-nums opacity-70">{{ $list->favorites_count }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </div>

            <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    @if($person)
                        @php($status = $statusFor($person))
                        <h2 id="activity-title" class="truncate text-lg font-semibold text-foreground">{{ $displayName($person->email) }}</h2>
                        @if(isset($names[$person->email]))
                            <p class="truncate text-[13px] text-muted-foreground">{{ $person->email }}</p>
                        @endif
                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-[13px]">
                            <span class="tabular-nums text-foreground">{{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}</span>
                            <span class="{{ $status['class'] }}">{{ $status['text'] }}</span>
                        </p>
                    @else
                        <h2 id="activity-title" class="text-lg font-semibold text-foreground">Everyone's favorites</h2>
                        <p class="mt-0.5 text-[13px] text-muted-foreground">
                            {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }} picked by {{ $lists->count() }} {{ Str::plural('person', $lists->count()) }}
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if($person)
                        <form method="POST" action="{{ route('studio.galleries.favorites.destroy', [$gallery->uuid, $person->id]) }}"
                              onsubmit="return confirm(@js('Remove ' . $person->email . '\'s ' . $person->favorites_count . ' favorites? This can\'t be undone.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger-ghost btn-sm">Remove</button>
                        </form>
                    @endif
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
    @endif

    {{-- ================= Downloads ================= --}}
    @if($view === 'downloads')
    <section aria-labelledby="activity-title" class="max-w-3xl">
        <header class="mb-4">
            <h2 id="activity-title" class="text-lg font-semibold text-foreground">Downloads</h2>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Every photo and whole-gallery download, newest first. The email shows when the client gave one for favorites.</p>
        </header>

        @if($downloads->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <p class="text-sm font-medium text-foreground">No downloads yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">When a client downloads a photo or the whole gallery, it shows here.</p>
            </div>
        @else
            <ul class="card divide-y divide-border">
                @foreach($downloads as $row)
                    <li class="flex items-center gap-4 px-4 py-3 sm:px-5">
                        <x-icon :name="$row->zip ? 'folder' : 'image'" class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-foreground">
                                @if($row->zip)
                                    Whole gallery @if($row->photos)<span class="text-muted-foreground">({{ $row->photos }} {{ Str::plural('photo', $row->photos) }})</span>@endif
                                @else
                                    {{ $row->filename ?? 'A photo' }}
                                @endif
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ $row->email ? $displayName($row->email) : 'Someone without an email' }}@if($row->viaLink), from a quick link @endif
                            </p>
                        </div>
                        <time class="shrink-0 text-xs tabular-nums text-muted-foreground" datetime="{{ $row->at->toIso8601String() }}">{{ $when($row->at) }}</time>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $downloadsPage->links() }}</div>
        @endif
    </section>
    @endif

    {{-- ================= Quick links ================= --}}
    @if($view === 'links')
    <section aria-labelledby="activity-title" class="max-w-3xl">
        <header class="mb-4">
            <h2 id="activity-title" class="text-lg font-semibold text-foreground">Quick links</h2>
            <p class="mt-0.5 text-[13px] text-muted-foreground">
                Links to a few chosen photos. They open with the link alone, even before you share the gallery, and stop when you delete them or the gallery expires.
            </p>
        </header>

        @if($links->isEmpty())
            <div class="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                <p class="text-sm font-medium text-foreground">No quick links yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">On the Photos tab, select photos and choose Share link. The link is copied and listed here.</p>
                <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="btn btn-outline btn-sm mt-4">Go to Photos</a>
            </div>
        @else
            <ul class="card divide-y divide-border">
                @foreach($links as $link)
                    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                        <div class="flex shrink-0 gap-1" aria-hidden="true">
                            @foreach($link->photos as $photo)
                                <span class="flex size-11 items-center justify-center bg-muted p-0.5">
                                    <img src="{{ $photo->getThumbnailUrl('xs') ?: $photo->getThumbnailUrl('sm') }}" alt="" loading="lazy" class="size-full object-contain">
                                </span>
                            @endforeach
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground">{{ $link->photos_count }} {{ Str::plural('photo', $link->photos_count) }}</p>
                            <p class="text-xs text-muted-foreground">
                                Made {{ $when($link->created_at) }}.
                                {{ $link->views_count ? 'Opened ' . $link->views_count . ' ' . Str::plural('time', $link->views_count) . ', last ' . $link->last_viewed_at->diffForHumans() . '.' : 'Not opened yet.' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" class="btn btn-outline btn-xs" @click="$clipboard(@js($link->url()), 'Link copied')"><x-icon name="copy" /> Copy link</button>
                            <a href="{{ $link->url() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs">Open</a>
                            <form method="POST" action="{{ route('studio.galleries.share-links.destroy', [$gallery->uuid, $link->id]) }}"
                                  onsubmit="return confirm('Delete this link? Anyone who has it can no longer open these photos.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger-ghost btn-xs">Delete</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    @endif

    </x-studio.gallery-workspace>
</div>
@endsection
