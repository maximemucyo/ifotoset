{{-- Studio gallery cover card. Expects: $gallery (coverPhoto, stats and user relations loaded). --}}
@php
    $vis = [
        'public'   => ['label' => 'Public',        'icon' => 'globe'],
        'private'  => ['label' => 'Private',       'icon' => 'lock'],
        'password' => ['label' => 'PIN protected', 'icon' => 'key'],
    ][$gallery->visibility] ?? ['label' => ucfirst((string) $gallery->visibility), 'icon' => 'eye'];
    $coverUrl = $gallery->getCoverUrl('md');
    $publicUrl = filled($gallery->user?->username) ? $gallery->public_url : null;
    $showUrl = route('studio.galleries.show', $gallery->uuid);
    $photoCount = $gallery->photo_count;
    $takenDown = $gallery->isTakenDown();
@endphp
<article class="group relative flex flex-col rounded-xl hover:z-10 focus-within:z-20 border border-border bg-card shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-border-strong hover:shadow-md">
    <a href="{{ $showUrl }}" class="relative block aspect-[4/3] overflow-hidden rounded-t-[11px] bg-muted" aria-label="Open {{ $gallery->title }}">
        @if($coverUrl)
            <img src="{{ $coverUrl }}" alt="" loading="lazy" decoding="async"
                 class="absolute inset-0 size-full object-cover transition duration-700 ease-out group-hover:scale-[1.04]">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/45 via-black/0 to-black/10"></div>
        @else
            <div class="absolute inset-0 bg-dots opacity-60 mask-radial"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                <span class="flex size-11 items-center justify-center rounded-xl border border-border bg-card shadow-xs transition group-hover:scale-105">
                    <x-icon name="images" class="size-5" />
                </span>
                <span class="text-xs">{{ $photoCount > 0 ? 'No cover selected' : 'No photos yet' }}</span>
            </div>
        @endif

        <div class="absolute left-3 top-3 flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-md bg-black/55 px-1.5 py-0.5 text-[11px] font-medium text-white backdrop-blur-sm">
                <x-icon :name="$vis['icon']" class="size-3" /> {{ $vis['label'] }}
            </span>
            @if($takenDown)
                <span class="inline-flex items-center gap-1 rounded-md bg-destructive px-1.5 py-0.5 text-[11px] font-medium text-white">
                    <x-icon name="ban" class="size-3" /> Taken down
                </span>
            @endif
        </div>

        @if($coverUrl)
            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-md bg-black/55 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-white backdrop-blur-sm">
                <x-icon name="image" class="size-3" /> {{ number_format($photoCount) }}
            </span>
        @endif
    </a>

    <div class="flex items-start gap-2 p-4">
        <div class="min-w-0 flex-1">
            <h3 class="truncate text-sm font-semibold text-foreground">
                <a href="{{ $showUrl }}" class="hover:underline hover:decoration-border-strong hover:underline-offset-4">{{ $gallery->title }}</a>
            </h3>
            <p class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                <span class="shrink-0 whitespace-nowrap tabular-nums">{{ number_format($photoCount) }} {{ \Illuminate\Support\Str::plural('photo', $photoCount) }}</span>
                <span class="text-subtle">&middot;</span>
                @if($gallery->client_name)
                    <span class="truncate">{{ $gallery->client_name }}</span>
                @elseif($gallery->event_date)
                    <span class="truncate">{{ $gallery->event_date->format('M j, Y') }}</span>
                @else
                    <span class="truncate">Updated {{ $gallery->updated_at->diffForHumans() }}</span>
                @endif
            </p>
        </div>

        <div x-data="dropdown" class="relative -mr-1.5 -mt-1" @click.outside="close()" @keydown.escape.window="close()">
            <button type="button" @click="toggle()" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" :class="open && 'bg-muted text-foreground'" aria-label="Gallery actions" :aria-expanded="open">
                <x-icon name="ellipsis" />
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="menu absolute right-0 top-full z-30 mt-1">
                <a href="{{ $showUrl }}" class="menu-item"><x-icon name="images" /> Manage photos</a>
                <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="menu-item"><x-icon name="settings" /> Gallery settings</a>
                @if($publicUrl)
                    <div class="menu-separator"></div>
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="menu-item"><x-icon name="eye" /> Preview as client <x-icon name="arrow-up-right" class="ml-auto !size-3.5" /></a>
                    <button type="button" class="menu-item" @click="$clipboard(@js($publicUrl), 'Gallery link copied'); close()"><x-icon name="link" /> Copy gallery link</button>
                @endif
            </div>
        </div>
    </div>
</article>
