@extends('layouts.app', ['title' => 'Overview · ifotoset'])

@section('content')
@php
    $tz = $user->timezone ?: config('app.timezone');
    try { $localNow = now()->setTimezone($tz); } catch (\Throwable $e) { $localNow = now(); }

    // ---- Getting started: the studio steps; booking steps are for those who take bookings ----
    $firstGallery = $recentGalleries->first();
    $steps = [
        ['key' => 'profile', 'title' => 'Complete your profile', 'description' => 'Bio, photo, location and specialties.', 'url' => route('studio.settings.index')],
        ['key' => 'gallery', 'title' => 'Create a gallery', 'description' => 'A home for one client\'s photos.', 'url' => route('studio.galleries.create')],
        ['key' => 'photos', 'title' => 'Upload photos', 'description' => 'Drop images into a gallery.', 'url' => $firstGallery ? route('studio.galleries.show', ['uuid' => $firstGallery->uuid, 'upload' => 1]) : route('studio.galleries.create')],
    ];
    $bookingSteps = [
        ['key' => 'availability', 'title' => 'Set your availability', 'description' => 'Clients book your open times.', 'url' => route('studio.availability.index')],
        ['key' => 'package', 'title' => 'Add a package', 'description' => 'What you offer, and the price.', 'url' => route('studio.packages.index')],
    ];
    $doneCount = collect($steps)->filter(fn ($s) => $setup[$s['key']] ?? false)->count();
    $nextStep = collect($steps)->first(fn ($s) => ! ($setup[$s['key']] ?? false));

    $kindIcons = ['bookings' => 'calendar-check', 'invites' => 'mail', 'expiring' => 'clock', 'empty' => 'images', 'picks' => 'heart'];
    $uploadTargets = $recentGalleries->reject->isTakenDown();
@endphp

<x-ui.page-header :title="$localNow->format('l, F j')">
    <x-slot:actions>
        @if($uploadTargets->isNotEmpty())
            <div x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" class="btn btn-outline btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="menu">
                    <x-icon name="upload" /> Upload <x-icon name="chevron-down" class="!size-3.5 text-muted-foreground" />
                </button>
                <div x-show="open" x-cloak role="menu"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     class="menu absolute right-0 top-full z-30 mt-1 w-64 sm:left-0 sm:right-auto">
                    <p class="px-2.5 pb-1 pt-1.5 text-xs text-muted-foreground">Add photos to</p>
                    @foreach($uploadTargets as $target)
                        <a href="{{ route('studio.galleries.show', ['uuid' => $target->uuid, 'upload' => 1]) }}" class="menu-item" role="menuitem">
                            <span class="block size-6 shrink-0 overflow-hidden bg-muted">
                                @if($cover = $target->getCoverUrl('sm'))
                                    <img src="{{ $cover }}" alt="" class="size-full object-cover" style="object-position: {{ $target->coverObjectPosition() }}">
                                @endif
                            </span>
                            <span class="truncate">{{ $target->title }}</span>
                        </a>
                    @endforeach
                    <div class="menu-separator"></div>
                    <button type="button" class="menu-item" role="menuitem" @click="close(); $dispatch('open-modal', 'new-gallery')"><x-icon name="plus" /> New gallery</button>
                </div>
            </div>
        @else
            <button type="button" class="btn btn-outline btn-sm" @click="$dispatch('open-modal', 'new-gallery')"><x-icon name="upload" /> Upload</button>
        @endif
        @if($user->username)
            <button type="button" class="btn btn-ghost btn-sm" @click="$clipboard(@js($user->public_url), 'Portfolio link copied')">
                <x-icon name="link" /> Copy portfolio link
            </button>
        @endif
    </x-slot:actions>
</x-ui.page-header>

{{-- Phone prompt for owners who signed up before it was asked --}}
@if(auth()->id() === $user->id && $user->shouldPromptForPhone())
    <section class="card mb-6 p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold text-foreground">Add a phone number to your account</h2>
                <p class="mt-0.5 text-[13px] text-muted-foreground">We'll use it for billing and important account updates.</p>
            </div>
            <form method="POST" action="{{ route('studio.settings.phone.dismiss') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remind me later"><x-icon name="x" /></button>
            </form>
        </div>
        <form method="POST" action="{{ route('studio.settings.phone') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start">
            @csrf
            <div class="sm:w-80">
                <x-ui.phone-input placeholder="788 123 456" :error="$errors->first('phone')" />
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </section>
@endif

<div class="grid gap-8 lg:grid-cols-3 lg:gap-6">
    {{-- Needs you: one row per thing waiting on the photographer --}}
    <section class="min-w-0 lg:col-span-2" aria-labelledby="needs-you-title">
        <h2 id="needs-you-title" class="mb-3 flex items-baseline gap-2 text-[15px] font-semibold text-foreground">
            Needs you
            @if($needsYou->isNotEmpty())
                <span class="text-[13px] font-normal tabular-nums text-muted-foreground">{{ $needsYou->count() }}</span>
            @endif
        </h2>

        @if($needsYou->isNotEmpty())
            <ul class="card divide-y divide-border overflow-hidden">
                @foreach($needsYou as $row)
                    <li>
                        <a href="{{ $row['url'] }}" class="group/row flex items-center gap-4 px-4 py-3.5 transition-colors hover:bg-muted/50 sm:px-5">
                            @if(($row['photos'] ?? collect())->isNotEmpty())
                                {{-- The client's picks, as a strip of contact-sheet frames --}}
                                <span class="flex h-12 w-[4.5rem] shrink-0 gap-0.5 sm:w-[6.75rem] max-sm:[&>*:nth-child(n+3)]:hidden" aria-hidden="true">
                                    @foreach($row['photos'] as $photo)
                                        <span class="h-full flex-1 overflow-hidden bg-muted"><img src="{{ $photo->getThumbnailUrl('sm') }}" alt="" loading="lazy" class="size-full object-cover"></span>
                                    @endforeach
                                </span>
                            @elseif(isset($row['gallery']) && ($cover = $row['gallery']->getCoverUrl('sm')))
                                <span class="block size-12 shrink-0 overflow-hidden bg-muted" aria-hidden="true">
                                    <img src="{{ $cover }}" alt="" loading="lazy" class="size-full object-cover" style="object-position: {{ $row['gallery']->coverObjectPosition() }}">
                                </span>
                            @else
                                <span class="flex size-12 shrink-0 items-center justify-center bg-muted text-muted-foreground" aria-hidden="true">
                                    <x-icon :name="$kindIcons[$row['kind']] ?? 'circle'" class="size-5" />
                                </span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-foreground">{{ $row['title'] }}</span>
                                <span class="mt-0.5 block truncate text-[13px] text-muted-foreground">{{ $row['detail'] }}</span>
                            </span>
                            <span class="hidden shrink-0 text-[13px] font-medium text-muted-foreground group-hover/row:text-foreground sm:inline">{{ $row['action'] }}</span>
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-subtle sm:hidden" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="card px-5 py-8">
                <p class="text-sm font-medium text-foreground">Nothing needs you right now.</p>
                <p class="mt-1 max-w-md text-[13px] text-muted-foreground">Favorites clients send, booking requests and galleries about to close will show here.</p>
            </div>
        @endif
    </section>

    <div class="flex min-w-0 flex-col gap-8">
        {{-- Sessions coming up --}}
        <section aria-labelledby="sessions-title">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 id="sessions-title" class="text-[15px] font-semibold text-foreground">Coming up</h2>
                <a href="{{ route('studio.bookings.index') }}" class="text-[13px] text-muted-foreground hover:text-foreground">Bookings</a>
            </div>
            @if($upcomingBookings->isNotEmpty())
                <ul class="card divide-y divide-border overflow-hidden">
                    @foreach($upcomingBookings as $booking)
                        @php
                            $status = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;
                            $starts = $booking->localStartsAt();
                        @endphp
                        <li>
                            <a href="{{ route('studio.bookings.index', ['status' => $status]) }}" class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/50">
                                <span class="w-14 shrink-0 text-[13px] leading-5 tabular-nums text-muted-foreground">
                                    <span class="block font-medium text-foreground">{{ $starts->isToday() ? 'Today' : ($starts->isTomorrow() ? 'Tomorrow' : $starts->format('D j')) }}</span>
                                    {{ $starts->format('g:i A') }}
                                </span>
                                <span class="min-w-0 flex-1 leading-5">
                                    <span class="block truncate text-[13px] font-medium text-foreground">{{ $booking->client->name ?? 'Guest client' }}</span>
                                    <span class="block truncate text-[13px] text-muted-foreground">{{ $booking->package->name ?? ($booking->title ?: 'Photo session') }}</span>
                                </span>
                                @if($status !== 'confirmed')
                                    <x-ui.badge variant="warning" class="shrink-0">{{ ucfirst($status) }}</x-ui.badge>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-[13px] text-muted-foreground">No sessions booked. <a href="{{ route('studio.availability.index') }}" class="link">Open times on your calendar</a> so clients can book you.</p>
            @endif
        </section>

        {{-- What clients did this week, one line per gallery --}}
        <section aria-labelledby="client-activity-title">
            <h2 id="client-activity-title" class="mb-3 text-[15px] font-semibold text-foreground">Client activity</h2>
            @if($clientActivity->isNotEmpty())
                <ul class="space-y-3">
                    @foreach($clientActivity as $activity)
                        @php
                            $parts = array_filter([
                                $activity->views ? $activity->views . ' ' . \Illuminate\Support\Str::plural('view', $activity->views) : null,
                                $activity->favorites ? $activity->favorites . ' ' . \Illuminate\Support\Str::plural('favorite', $activity->favorites) : null,
                                $activity->downloads ? $activity->downloads . ' ' . \Illuminate\Support\Str::plural('download', $activity->downloads) : null,
                            ]);
                        @endphp
                        <li>
                            <a href="{{ route('studio.galleries.activity', $activity->gallery->uuid) }}" class="group/act flex items-center gap-3">
                                <span class="block size-10 shrink-0 overflow-hidden bg-muted" aria-hidden="true">
                                    @if($cover = $activity->gallery->getCoverUrl('sm'))
                                        <img src="{{ $cover }}" alt="" loading="lazy" class="size-full object-cover" style="object-position: {{ $activity->gallery->coverObjectPosition() }}">
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1 leading-5">
                                    <span class="block truncate text-[13px] font-medium text-foreground group-hover/act:underline group-hover/act:underline-offset-4">{{ $activity->gallery->title }}</span>
                                    <span class="block truncate text-[13px] text-muted-foreground">{{ ucfirst(implode(', ', $parts)) }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ $activity->last_at->diffForHumans(short: true) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-[13px] text-muted-foreground">No client visits in the last 7 days.</p>
            @endif
        </section>
    </div>
</div>

{{-- Getting started, until the studio steps are done or the photographer hides it --}}
@if($showChecklist)
    <section class="card mt-8 p-5" aria-labelledby="setup-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="setup-title" class="text-[15px] font-semibold text-foreground">Get your studio ready</h2>
                <p class="mt-0.5 text-[13px] text-muted-foreground">{{ $doneCount }} of {{ count($steps) }} done.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($nextStep)
                    <a href="{{ $nextStep['url'] }}" class="btn btn-outline btn-sm">{{ $nextStep['title'] }}</a>
                @endif
                <form method="POST" action="{{ route('studio.dashboard.checklist.dismiss') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm text-muted-foreground">Hide</button>
                </form>
            </div>
        </div>
        <div class="mt-4 grid gap-x-6 gap-y-1 sm:grid-cols-2">
            <ol class="space-y-1">
                @foreach($steps as $step)
                    @include('studio.partials.setup-step', ['step' => $step, 'done' => $setup[$step['key']] ?? false])
                @endforeach
            </ol>
            <div>
                <p class="mb-1 mt-3 text-xs text-muted-foreground sm:mt-0">Only if you take bookings</p>
                <ul class="space-y-1">
                    @foreach($bookingSteps as $step)
                        @include('studio.partials.setup-step', ['step' => $step, 'done' => $setup[$step['key']] ?? false])
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endif

{{-- Galleries, each with its stage and what clients did --}}
<section class="mt-10" aria-labelledby="galleries-title">
    <div class="mb-3 flex items-baseline justify-between gap-4">
        <h2 id="galleries-title" class="text-[15px] font-semibold text-foreground">Recent galleries</h2>
        @if($recentGalleries->isNotEmpty())
            <a href="{{ route('studio.galleries.index') }}" class="text-[13px] text-muted-foreground hover:text-foreground">All galleries</a>
        @endif
    </div>

    @if($recentGalleries->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 max-sm:[&>*:nth-child(n+5)]:hidden">
            @foreach($recentGalleries as $gallery)
                @include('studio.partials.gallery-card', ['gallery' => $gallery])
            @endforeach
        </div>
    @else
        <x-ui.empty-state icon="images" title="No galleries yet"
                          description="Create a gallery, add your photos and share the link with your client."
                          action-label="New gallery" :action-url="route('studio.galleries.create')" />
    @endif
</section>
@endsection
