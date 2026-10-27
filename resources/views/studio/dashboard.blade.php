@extends('layouts.app', ['title' => 'Overview · ifotoset'])

@section('content')
@php
    // ---- Greeting -------------------------------------------------------
    $tz = $user->timezone ?: config('app.timezone');
    try { $localNow = now()->setTimezone($tz); } catch (\Throwable $e) { $localNow = now(); }
    $hour = (int) $localNow->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::before(trim($user->name ?? ''), ' ') ?: 'there';

    // ---- Storage --------------------------------------------------------
    $fmtBytes = function ($bytes) {
        // Binary units, matching how plan limits are defined (1 GB = 1024^3 bytes).
        $bytes = (float) $bytes;
        foreach (['TB' => 1024 ** 4, 'GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                $v = $bytes / $size;
                $dec = $v < 10 ? 2 : ($v < 100 ? 1 : 0);
                $out = number_format($v, $dec);
                return ($dec > 0 ? rtrim(rtrim($out, '0'), '.') : $out) . ' ' . $unit;
            }
        }
        return (int) $bytes . ' B';
    };
    $storagePct = (float) ($storage['percentage'] ?? $storage['percent_used'] ?? 0);
    $isUnlimited = (bool) ($storage['is_unlimited'] ?? false);
    $limitBytes = $storage['limit_bytes'] ?? null;
    $usedLabel = $fmtBytes($storage['used_bytes'] ?? 0);
    $limitLabel = $isUnlimited ? 'Unlimited' : ($limitBytes ? $fmtBytes($limitBytes) : '—');
    $planName = $storage['plan_name'] ?? ($user->plan?->name ?? 'Free');
    $isFreePlan = (bool) ($storage['is_free'] ?? (! $user->plan || $user->plan->slug === 'free'));
    $hasUsage = ($storage['used_bytes'] ?? 0) > 0;
    $pctLabel = ($hasUsage && $storagePct < 1) ? '<1' : (string) round($storagePct);
    $storageTone = $storagePct >= 90 ? 'bg-destructive' : ($storagePct >= 75 ? 'bg-warning' : 'bg-brand');

    // ---- Getting started ------------------------------------------------
    $firstGallery = $recentGalleries->first();
    $steps = [
        ['key' => 'profile', 'title' => 'Complete your profile', 'description' => 'Add a bio, profile photo, location and specialties.', 'icon' => 'user', 'url' => route('studio.settings.index')],
        ['key' => 'gallery', 'title' => 'Create your first gallery', 'description' => 'A home for a client\'s photos.', 'icon' => 'images', 'url' => route('studio.galleries.create')],
        ['key' => 'photos', 'title' => 'Upload photos', 'description' => 'Drag and drop images into a gallery.', 'icon' => 'upload-cloud', 'url' => $firstGallery ? route('studio.galleries.show', $firstGallery->uuid) : route('studio.galleries.create')],
        ['key' => 'availability', 'title' => 'Set your availability', 'description' => 'Let clients book open time slots.', 'icon' => 'calendar', 'url' => route('studio.availability.index')],
        ['key' => 'package', 'title' => 'Add a booking package', 'description' => 'Describe what you offer and for how much.', 'icon' => 'package', 'url' => route('studio.packages.index')],
    ];
    $setup = $setup ?? [];
    $doneCount = collect($steps)->filter(fn ($s) => $setup[$s['key']] ?? false)->count();
    $setupComplete = $doneCount === count($steps);
    $nextStep = collect($steps)->first(fn ($s) => ! ($setup[$s['key']] ?? false));
@endphp

<x-ui.page-header :title="$greeting . ', ' . $firstName">
    <x-slot:meta>
        <p class="mt-1.5 text-sm text-muted-foreground">
            {{ $localNow->format('l, F j') }}
            @if($pendingBookings > 0)
                <span class="text-subtle">&middot;</span>
                <a href="{{ route('studio.bookings.index', ['status' => 'pending']) }}" class="link font-medium">{{ $pendingBookings }} {{ \Illuminate\Support\Str::plural('booking', $pendingBookings) }} awaiting review</a>
            @elseif($upcomingBookings->isNotEmpty())
                <span class="text-subtle">&middot;</span>
                Next session {{ $upcomingBookings->first()->starts_at->diffForHumans() }}
            @else
                <span class="text-subtle">&middot;</span>
                Here's what's happening in your studio.
            @endif
        </p>
    </x-slot:meta>
    <x-slot:actions>
        @if($user->username)
            <button type="button" class="btn btn-outline btn-sm" @click="$clipboard(@js($user->public_url), 'Portfolio link copied')">
                <x-icon name="link" /> Copy portfolio link
            </button>
        @endif
        <a href="{{ route('studio.analytics.index') }}" class="btn btn-outline btn-sm"><x-icon name="chart-column" /> Analytics</a>
    </x-slot:actions>
</x-ui.page-header>

{{-- Phone prompt for owners who signed up before it was asked --}}
@if(auth()->id() === $user->id && $user->shouldPromptForPhone())
    <section class="card mb-6 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20"><x-icon name="phone" class="size-4" /></span>
                <div>
                    <h2 class="text-[15px] font-semibold text-foreground">Add a phone number to your account</h2>
                    <p class="mt-0.5 text-[13px] text-muted-foreground">We'll use it for billing and important account updates.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('studio.settings.phone.dismiss') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remind me later"><x-icon name="x" /></button>
            </form>
        </div>
        <form method="POST" action="{{ route('studio.settings.phone') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:pl-[3.375rem]">
            @csrf
            <div class="sm:w-80">
                <x-ui.phone-input placeholder="788 123 456" :error="$errors->first('phone')" />
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </section>
@endif

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-studio.stat-card label="Galleries" :value="number_format($totalGalleries)" icon="images"
                        :href="route('studio.galleries.index')"
                        :hint="$totalGalleries === 0 ? 'None yet' : 'All client collections'" />
    <x-studio.stat-card label="Photos delivered" :value="number_format($totalPhotos)" icon="image"
                        :hint="$totalPhotos === 0 ? 'Upload to get started' : 'Across all galleries'" />
    <x-studio.stat-card label="Pending bookings" :value="number_format($pendingBookings)" icon="calendar"
                        :href="route('studio.bookings.index', ['status' => 'pending'])"
                        :hint="$pendingBookings > 0 ? 'Awaiting your review' : 'You\'re all caught up'" />
    <x-studio.stat-card label="Clients" :value="number_format($totalClients ?? 0)" icon="users"
                        :href="route('studio.clients.index')"
                        :hint="($totalClients ?? 0) === 0 ? 'Add your first client' : 'In your address book'" />
</div>

{{-- Getting started --}}
@unless($setupComplete)
    <section class="card mt-6 overflow-hidden" x-data="{ open: true }">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3.5">
                <div class="relative flex size-11 shrink-0 items-center justify-center">
                    @php $ring = 2 * M_PI * 18; @endphp
                    <svg viewBox="0 0 44 44" class="absolute inset-0 -rotate-90" aria-hidden="true">
                        <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="4" class="text-muted" />
                        <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" class="text-brand transition-all duration-700"
                                stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring * (1 - $doneCount / count($steps)) }}" />
                    </svg>
                    <span class="text-[11px] font-semibold tabular-nums text-foreground">{{ $doneCount }}/{{ count($steps) }}</span>
                </div>
                <div>
                    <h2 class="text-[15px] font-semibold text-foreground">Get your studio ready</h2>
                    <p class="mt-0.5 text-[13px] text-muted-foreground">
                        {{ count($steps) - $doneCount }} {{ \Illuminate\Support\Str::plural('step', count($steps) - $doneCount) }} left to start delivering galleries and taking bookings.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($nextStep)
                    <a href="{{ $nextStep['url'] }}" class="btn btn-primary btn-sm">{{ $nextStep['title'] }} <x-icon name="arrow-right" /></a>
                @endif
                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="open = !open" :aria-expanded="open" aria-label="Toggle checklist">
                    <x-icon name="chevron-down" class="transition-transform" ::class="open && 'rotate-180'" />
                </button>
            </div>
        </div>
        <div x-show="open" x-collapse.duration.200ms class="border-t border-border bg-muted/30">
            <ol class="grid divide-y divide-border sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-5 lg:divide-x">
                @foreach($steps as $i => $step)
                    @php $done = $setup[$step['key']] ?? false; @endphp
                    <li class="sm:border-border max-lg:sm:[&:nth-child(n+3)]:border-t max-lg:sm:[&:nth-child(even)]:border-l">
                        <a href="{{ $step['url'] }}" class="group/step flex h-full items-start gap-3 p-4 transition-colors hover:bg-card">
                            @if($done)
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success text-white"><x-icon name="check" class="size-3" /></span>
                            @else
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border border-dashed border-border-strong text-[10px] font-semibold tabular-nums text-muted-foreground group-hover/step:border-foreground group-hover/step:text-foreground">{{ $i + 1 }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-[13px] font-medium {{ $done ? 'text-muted-foreground line-through decoration-border-strong' : 'text-foreground' }}">{{ $step['title'] }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">{{ $step['description'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endunless

{{-- Recent galleries --}}
<section class="mt-8">
    <div class="mb-4 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-[15px] font-semibold text-foreground">Recent galleries</h2>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Your latest client collections.</p>
        </div>
        @if($recentGalleries->isNotEmpty())
            <a href="{{ route('studio.galleries.index') }}" class="btn btn-ghost btn-sm text-muted-foreground">View all <x-icon name="arrow-right" /></a>
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
                          description="Create a gallery, upload your photos and share a private link with your client in minutes."
                          action-label="Create your first gallery" :action-url="route('studio.galleries.create')" />
    @endif
</section>

{{-- Bookings + side column --}}
<div class="mt-8 grid gap-4 lg:grid-cols-3 lg:gap-6">
    <section class="card overflow-hidden lg:col-span-2">
        <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
            <div>
                <h2 class="text-[15px] font-semibold text-foreground">Upcoming sessions</h2>
                <p class="mt-0.5 text-[13px] text-muted-foreground">Confirmed and pending bookings.</p>
            </div>
            <a href="{{ route('studio.bookings.index') }}" class="btn btn-ghost btn-sm text-muted-foreground">All bookings <x-icon name="arrow-right" /></a>
        </div>

        @if($upcomingBookings->isNotEmpty())
            <ul class="divide-y divide-border">
                @foreach($upcomingBookings as $booking)
                    @php
                        $status = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;
                        $starts = $booking->localStartsAt();
                    @endphp
                    <li>
                        <a href="{{ route('studio.bookings.index', ['status' => $status]) }}" class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-muted/50">
                            <div class="flex w-12 shrink-0 flex-col items-center overflow-hidden rounded-lg border border-border bg-card text-center shadow-xs">
                                <span class="w-full bg-muted py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{{ $starts->format('M') }}</span>
                                <span class="py-1 font-display text-lg font-semibold leading-none tabular-nums text-foreground">{{ $starts->format('j') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ $booking->client->name ?? 'Guest client' }}</p>
                                <p class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                                    <x-icon name="clock" class="size-3 shrink-0" />
                                    <span class="shrink-0 tabular-nums">{{ $starts->format('D · g:i A') }}</span>
                                    <span class="text-subtle">&middot;</span>
                                    <span class="truncate">{{ $booking->package->name ?? ($booking->title ?: 'Photo session') }}</span>
                                </p>
                            </div>
                            <x-ui.badge :variant="$status === 'confirmed' ? 'success' : 'warning'" dot class="shrink-0">{{ ucfirst($status) }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex flex-col items-center px-6 py-12 text-center">
                <span class="flex size-11 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-xs"><x-icon name="calendar" class="size-5" /></span>
                <p class="mt-3 text-sm font-medium text-foreground">No upcoming sessions</p>
                <p class="mt-1 max-w-xs text-[13px] text-muted-foreground">Open time slots on your calendar so clients can book you from your portfolio.</p>
                <a href="{{ route('studio.availability.index') }}" class="btn btn-outline btn-sm mt-4"><x-icon name="calendar" /> Set availability</a>
            </div>
        @endif
    </section>

    <div class="flex flex-col gap-4 lg:gap-6">
        {{-- Storage --}}
        <section class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-semibold text-foreground">Storage</h2>
                <span class="badge {{ $isFreePlan ? 'badge-neutral' : 'badge-brand' }}"><x-icon name="crown" class="size-3" /> {{ $planName }}</span>
            </div>
            <div class="mt-4 flex items-baseline gap-1.5">
                <span class="font-display text-2xl font-semibold tracking-tight tabular-nums text-foreground">{{ $usedLabel }}</span>
                <span class="text-[13px] text-muted-foreground">of {{ $limitLabel }}</span>
                @unless($isUnlimited)<span class="ml-auto text-xs tabular-nums text-muted-foreground">{{ $pctLabel }}%</span>@endunless
            </div>
            @unless($isUnlimited)
                @php
                    $activePct = (float) ($storage['active_percent'] ?? $storagePct);
                    $trashPct = (float) ($storage['trash_percent'] ?? 0);
                @endphp
                <div class="mt-3 flex h-2 w-full overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ round($storagePct) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Storage used">
                    <span class="h-full {{ $storageTone }} transition-[width] duration-500" style="width: {{ min(100, max($hasUsage ? 1.5 : 0, $activePct)) }}%"></span>
                    @if($trashPct > 0)
                        <span class="h-full bg-border-strong" style="width: {{ min(100, $trashPct) }}%"></span>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-1.5 text-muted-foreground"><span class="size-2 rounded-full {{ $storageTone }}"></span> Photos <span class="ml-auto font-medium tabular-nums text-foreground">{{ $fmtBytes($storage['active_bytes'] ?? 0) }}</span></div>
                    <div class="flex items-center gap-1.5 text-muted-foreground"><span class="size-2 rounded-full bg-border-strong"></span> Trash <span class="ml-auto font-medium tabular-nums text-foreground">{{ $fmtBytes($storage['trash_bytes'] ?? 0) }}</span></div>
                </div>
            @endunless
            <div class="mt-4 flex gap-2 border-t border-border pt-4">
                @cannot('studio.billing')
                    {{-- Team members: the owner handles the plan --}}
                @elseif($isFreePlan || $storagePct >= 75)
                    <a href="{{ route('studio.billing.index') }}" class="btn btn-brand btn-sm flex-1"><x-icon name="zap" /> Upgrade plan</a>
                @else
                    <a href="{{ route('studio.billing.index') }}" class="btn btn-outline btn-sm flex-1">Manage plan</a>
                @endcannot
                <a href="{{ route('studio.trash.index') }}" class="btn btn-ghost btn-sm"><x-icon name="trash-2" /> Trash</a>
            </div>
        </section>

        {{-- Quick actions --}}
        <section class="card p-2">
            <p class="menu-label px-3 pt-2">Quick actions</p>
            @foreach([
                ['url' => route('studio.bookings.index'), 'icon' => 'calendar-check', 'label' => 'Bookings', 'hint' => 'Requests and schedule'],
                ['url' => route('studio.availability.index'), 'icon' => 'clock', 'label' => 'Availability', 'hint' => 'Hours and blocked dates'],
                ['url' => route('studio.packages.index'), 'icon' => 'package', 'label' => 'Packages', 'hint' => 'Services and pricing'],
                ['url' => route('studio.settings.index'), 'icon' => 'settings', 'label' => 'Studio settings', 'hint' => 'Profile and notifications'],
            ] as $qa)
                <a href="{{ $qa['url'] }}" class="group/qa flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-muted">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-xs transition-colors group-hover/qa:text-foreground"><x-icon :name="$qa['icon']" class="size-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-medium text-foreground">{{ $qa['label'] }}</span>
                        <span class="block truncate text-xs text-muted-foreground">{{ $qa['hint'] }}</span>
                    </span>
                    <x-icon name="chevron-right" class="size-4 text-subtle transition-transform group-hover/qa:translate-x-0.5 group-hover/qa:text-foreground" />
                </a>
            @endforeach
        </section>
    </div>
</div>
@endsection
