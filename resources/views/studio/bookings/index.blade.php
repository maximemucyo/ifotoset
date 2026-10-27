@extends('layouts.app', ['title' => 'Bookings · ifotoset'])

@section('content')
@php
    $currentStatus = request('status', 'all');
    $statusOf = fn ($b) => $b->status instanceof \BackedEnum ? $b->status->value : (string) $b->status;

    // Summary figures: a single grouped aggregate (no per-row queries).
    $summary = \App\Models\Booking::where('user_id', request()->account()->id)
        ->visibleInStudio()
        ->toBase()
        ->selectRaw('status, currency, COUNT(*) as total, COALESCE(SUM(price), 0) as amount')
        ->groupBy('status', 'currency')
        ->get();
    $countFor = fn (string $s) => (int) $summary->where('status', $s)->sum('total');
    $counts = [
        'all' => (int) $summary->sum('total'),
        'pending' => $countFor('pending'),
        'confirmed' => $countFor('confirmed'),
        'completed' => $countFor('completed'),
        'cancelled' => $countFor('cancelled'),
    ];
    // Revenue from confirmed + completed sessions, in the most-used currency.
    $earned = $summary->whereIn('status', ['confirmed', 'completed'])->groupBy('currency')->map(fn ($rows) => (float) $rows->sum('amount'))->sortDesc();
    $revenueCurrency = $earned->keys()->first() ?? 'RWF';
    $revenue = (float) ($earned->first() ?? 0);
    $fmtMoney = function ($amount) {
        $amount = (float) $amount;
        if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
        if ($amount >= 10000) return number_format($amount / 1000, 0) . 'K';
        return number_format($amount, 0);
    };

    $tabs = ['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
@endphp

<div x-data="{ newBookingOpen: {{ old('date') ? 'true' : 'false' }} }">
<x-ui.page-header title="Bookings" description="Review requests, confirm sessions and keep track of what's coming up.">
    <x-slot:actions>
        @php $bookingLink = route('subdomain.booking', ['username' => request()->account()->username]); @endphp
        <button type="button" class="btn btn-outline" @click="$clipboard(@js($bookingLink), 'Booking link copied')" title="{{ $bookingLink }}">
            <x-icon name="link" /> Copy booking link
        </button>
        <a href="{{ route('studio.availability.index') }}" class="btn btn-outline">
            <x-icon name="calendar-check" /> Availability
        </a>
        <button type="button" class="btn btn-primary" @click="newBookingOpen = true">
            <x-icon name="plus" /> New booking
        </button>
    </x-slot:actions>
</x-ui.page-header>

@if(session('error'))
    <div class="alert alert-danger mb-6"><x-icon name="circle-alert" /> <span>{{ session('error') }}</span></div>
@endif
@if($errors->any())
    <div class="alert alert-danger mb-6"><x-icon name="circle-alert" /> <span>{{ $errors->first() }}</span></div>
@endif

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-studio.stat-card label="Awaiting review" :value="number_format($counts['pending'])" icon="clock"
        :hint="$counts['pending'] > 0 ? 'Needs your confirmation' : 'You\'re all caught up'"
        :href="route('studio.bookings.index', ['status' => 'pending'])" />
    <x-studio.stat-card label="Confirmed" :value="number_format($counts['confirmed'])" icon="calendar-check"
        hint="Upcoming & scheduled" :href="route('studio.bookings.index', ['status' => 'confirmed'])" />
    <x-studio.stat-card label="Completed" :value="number_format($counts['completed'])" icon="circle-check"
        hint="Sessions delivered" :href="route('studio.bookings.index', ['status' => 'completed'])" />
    <x-studio.stat-card label="Booked revenue" icon="wallet"
        :value="$revenue > 0 ? $revenueCurrency . ' ' . $fmtMoney($revenue) : '—'"
        hint="Confirmed + completed" />
</div>

{{-- Toolbar --}}
<div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="tabs" aria-label="Filter bookings by status">
            @foreach($tabs as $key => $label)
                <a href="{{ route('studio.bookings.index', $key === 'all' ? [] : ['status' => $key]) }}"
                   class="tab whitespace-nowrap" @if($currentStatus === $key) aria-selected="true" aria-current="page" @endif>
                    {{ $label }}
                    <span class="rounded px-1 text-[11px] tabular-nums {{ $currentStatus === $key ? 'bg-muted text-foreground' : 'text-subtle' }}">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </div>
    @if($bookings->total() > 0)
        <p class="text-[13px] text-muted-foreground">
            Showing <span class="font-medium text-foreground tabular-nums">{{ $bookings->firstItem() }}–{{ $bookings->lastItem() }}</span> of <span class="tabular-nums">{{ $bookings->total() }}</span>
        </p>
    @endif
</div>

@if($bookings->isEmpty())
    <div class="mt-4">
        @if($currentStatus !== 'all' && $counts['all'] > 0)
            <x-ui.empty-state icon="funnel"
                title="No {{ strtolower($tabs[$currentStatus] ?? $currentStatus) }} bookings"
                description="Nothing matches this filter right now. Try another status or view everything.">
                <a href="{{ route('studio.bookings.index') }}" class="btn btn-outline btn-sm mt-5">View all bookings</a>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state icon="calendar"
                title="No bookings yet"
                description="When clients book a session from your public profile, requests land here for you to confirm. Set your hours and create a package to start taking bookings.">
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('studio.packages.index') }}" class="btn btn-primary btn-sm"><x-icon name="package" /> Create a package</a>
                    <a href="{{ route('studio.availability.index') }}" class="btn btn-outline btn-sm"><x-icon name="clock" /> Set working hours</a>
                </div>
            </x-ui.empty-state>
        @endif
    </div>
@else
    {{-- Desktop table --}}
    <div class="card mt-4 hidden overflow-hidden md:block">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Session</th>
                        <th>Date & time</th>
                        <th class="text-right">Price</th>
                        <th>Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $booking)
                        @php $status = $statusOf($booking); @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$booking->client->name ?? 'Guest'" size="sm" />
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-foreground">{{ $booking->client->name ?? 'Guest client' }}</div>
                                        <div class="truncate text-xs text-muted-foreground">{{ $booking->client->email ?? ($booking->client->phone ?? 'No contact details') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="max-w-[220px] truncate font-medium text-foreground">{{ $booking->package->name ?? ($booking->title ?: 'Custom session') }}</div>
                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                    @if($booking->location)
                                        <x-icon name="map-pin" class="size-3" /> <span class="max-w-[180px] truncate">{{ $booking->location }}</span>
                                    @elseif($booking->package?->duration_minutes)
                                        <x-icon name="timer" class="size-3" /> {{ $booking->package->duration_minutes }} min
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="flex w-10 shrink-0 flex-col items-center overflow-hidden rounded-md border border-border bg-card text-center leading-none">
                                        <span class="w-full bg-muted py-0.5 text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">{{ $booking->localStartsAt()->format('M') }}</span>
                                        <span class="py-1 font-display text-sm font-semibold tabular-nums text-foreground">{{ $booking->localStartsAt()->format('j') }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="whitespace-nowrap text-[13px] font-medium text-foreground">{{ $booking->localStartsAt()->format('D, M j') }}</div>
                                        <div class="whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                                            {{ $booking->localStartsAt()->format('g:i A') }}@if($booking->ends_at) – {{ $booking->localEndsAt()->format('g:i A') }}@endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right">
                                @if($booking->price)
                                    <div class="whitespace-nowrap font-medium tabular-nums text-foreground">{{ number_format($booking->price, 0) }} <span class="text-xs font-normal text-muted-foreground">{{ $booking->currency }}</span></div>
                                @else
                                    <span class="text-subtle">—</span>
                                @endif
                                @include('studio.partials.business.booking-deposit', ['booking' => $booking])
                            </td>
                            <td>
                                @include('studio.partials.business.booking-status', ['status' => $status])
                                @if($status === 'confirmed' && $booking->starts_at->isFuture())
                                    <div class="mt-1 whitespace-nowrap text-[11px] text-muted-foreground">in {{ $booking->starts_at->diffForHumans(null, true) }}</div>
                                @endif
                            </td>
                            <td>
                                @include('studio.partials.business.booking-actions', ['booking' => $booking, 'status' => $status])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile cards --}}
    <ul class="mt-4 space-y-3 md:hidden">
        @foreach($bookings as $booking)
            @php $status = $statusOf($booking); @endphp
            <li class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.avatar :name="$booking->client->name ?? 'Guest'" size="sm" />
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-foreground">{{ $booking->client->name ?? 'Guest client' }}</div>
                            <div class="truncate text-xs text-muted-foreground">{{ $booking->package->name ?? ($booking->title ?: 'Custom session') }}</div>
                        </div>
                    </div>
                    @include('studio.partials.business.booking-status', ['status' => $status])
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3 text-xs">
                    <div>
                        <dt class="text-muted-foreground">When</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $booking->localStartsAt()->format('D, M j') }}</dd>
                        <dd class="tabular-nums text-muted-foreground">{{ $booking->localStartsAt()->format('g:i A') }}@if($booking->ends_at) – {{ $booking->localEndsAt()->format('g:i A') }}@endif</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Price</dt>
                        <dd class="mt-0.5 font-medium tabular-nums text-foreground">
                            @if($booking->price){{ $booking->currency }} {{ number_format($booking->price, 0) }}@else — @endif
                        </dd>
                        <dd>@include('studio.partials.business.booking-deposit', ['booking' => $booking])</dd>
                    </div>
                </dl>
                @if($status !== 'completed' && $status !== 'cancelled')
                    <div class="mt-3 border-t border-border pt-3">
                        @include('studio.partials.business.booking-actions', ['booking' => $booking, 'status' => $status, 'compact' => true])
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if($bookings->hasPages())
        <div class="mt-6">{{ $bookings->withQueryString()->links() }}</div>
    @endif
@endif

@include('studio.partials.business.booking-create-modal')
@include('studio.partials.business.confirm-dialog')
</div>
@endsection
