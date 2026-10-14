@extends('layouts.admin', ['title' => 'Support · Admin'])

@section('content')
@php
    $ticketCollection = collect($tickets);
    $counts = [
        'all' => $ticketCollection->count(),
        'Open' => $ticketCollection->where('status', 'Open')->count(),
        'In Progress' => $ticketCollection->where('status', 'In Progress')->count(),
        'Resolved' => $ticketCollection->where('status', 'Resolved')->count(),
    ];
    $priorityStyle = [
        'Urgent' => ['badge' => 'danger', 'bar' => 'bg-destructive'],
        'High' => ['badge' => 'danger', 'bar' => 'bg-destructive'],
        'Medium' => ['badge' => 'warning', 'bar' => 'bg-warning'],
        'Low' => ['badge' => 'neutral', 'bar' => 'bg-border-strong'],
    ];
    $statusVariant = ['Open' => 'info', 'In Progress' => 'warning', 'Resolved' => 'success'];
@endphp

<div x-data="{ filter: 'all' }">
    <x-ui.page-header title="Support" description="Inquiries from photographers, studios and booking clients." />

    <div class="alert alert-info mb-6">
        <x-icon name="info" />
        <div>
            <p class="font-medium">Sample tickets</p>
            <p class="mt-0.5 text-[13px] opacity-90">The support inbox isn't connected to a ticketing backend yet, so the tickets below are placeholders to preview the desk layout.</p>
        </div>
    </div>

    {{-- Status overview --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-studio.stat-card label="All tickets" :value="$counts['all']" icon="inbox" />
        <x-studio.stat-card label="Open" :value="$counts['Open']" icon="mail" />
        <x-studio.stat-card label="In progress" :value="$counts['In Progress']" icon="message-square" />
        <x-studio.stat-card label="Resolved" :value="$counts['Resolved']" icon="circle-check" />
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div class="tabs max-w-full overflow-x-auto scrollbar-none" role="tablist">
                @foreach(['all' => 'All', 'Open' => 'Open', 'In Progress' => 'In progress', 'Resolved' => 'Resolved'] as $key => $label)
                    <button type="button" role="tab" class="tab whitespace-nowrap" :aria-selected="filter === @js($key)" @click="filter = @js($key)">
                        {{ $label }}
                        <span class="rounded bg-muted px-1 text-[11px] tabular-nums text-muted-foreground">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </div>
            <p class="hidden text-xs text-muted-foreground sm:block">Sorted by last update</p>
        </div>

        @if($ticketCollection->isEmpty())
            <div class="p-4 sm:p-5">
                <x-ui.empty-state icon="life-buoy" title="Inbox zero" description="No support tickets are open right now." />
            </div>
        @else
            <ul class="divide-y divide-border">
                @foreach($tickets as $tk)
                    @php($p = $priorityStyle[$tk['priority']] ?? $priorityStyle['Low'])
                    <li x-show="filter === 'all' || filter === @js($tk['status'])"
                        class="relative flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-start sm:gap-4 sm:px-5">
                        <span class="absolute inset-y-3 left-0 w-0.5 rounded-r {{ $p['bar'] }}" aria-hidden="true"></span>

                        <x-ui.avatar :name="$tk['user']" size="md" class="hidden sm:inline-flex" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono text-xs text-muted-foreground">{{ $tk['id'] }}</span>
                                <h3 class="min-w-0 truncate text-sm font-medium text-foreground">{{ $tk['subject'] }}</h3>
                            </div>
                            <p class="mt-1 line-clamp-2 text-[13px] leading-relaxed text-muted-foreground">{{ $tk['message'] }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span class="font-medium text-foreground">{{ $tk['user'] }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="at-sign" class="size-3" />{{ $tk['email'] }}</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end sm:gap-1.5">
                            <div class="flex items-center gap-1.5">
                                <x-ui.badge :variant="$p['badge']">{{ $tk['priority'] }}</x-ui.badge>
                                <x-ui.badge :variant="$statusVariant[$tk['status']] ?? 'neutral'" dot>{{ $tk['status'] }}</x-ui.badge>
                            </div>
                            <span class="ml-auto text-xs text-muted-foreground sm:ml-0">{{ $tk['date'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
