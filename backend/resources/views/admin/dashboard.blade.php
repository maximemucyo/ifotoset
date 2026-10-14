@extends('layouts.admin', ['title' => 'Overview · Admin · ifotoset'])

@section('content')
@php
    $fmtBytes = function ($b) {
        $b = (float) $b; $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return ($i === 0 ? (int) $b : number_format($b, 1)) . ' ' . $u[$i];
    };
    $processing = (int) ($queueSummary['processing'] ?? 0);
    $queued = (int) ($queueSummary['queued'] ?? 0);
    $failedToday = (int) ($queueSummary['failed_today'] ?? 0);
    $health = $failedToday > 0 ? 'degraded' : ($processing + $queued > 0 ? 'busy' : 'healthy');
    $paymentVariant = fn ($s) => match ($s) {
        'completed', 'paid', 'succeeded' => 'success',
        'pending', 'processing', 'initiated', 'submitted' => 'warning',
        'failed', 'rejected', 'cancelled', 'canceled', 'expired' => 'danger',
        default => 'neutral',
    };
@endphp

<x-ui.page-header eyebrow="Admin console" title="Platform overview" description="Accounts, galleries, storage and revenue across every studio on ifotoset.">
    <x-slot:actions>
        <a href="{{ route('admin.analytics.index') }}" class="btn btn-outline btn-sm"><x-icon name="chart-column" /> Analytics</a>
        <a href="{{ route('admin.users.index') }}" class="btn btn-primary btn-sm"><x-icon name="users" /> Manage users</a>
    </x-slot:actions>
</x-ui.page-header>

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <x-studio.stat-card label="Registered users" :value="number_format($totalUsers)" icon="users"
        :hint="($newUsers30d ?? 0) . ' joined in the last 30 days'" :href="route('admin.users.index')" />
    <x-studio.stat-card label="Galleries" :value="number_format($totalGalleries)" icon="images"
        :hint="($takenDownGalleries ?? 0) > 0 ? $takenDownGalleries . ' taken down' : 'None taken down'" :href="route('admin.galleries.index')" />
    <x-studio.stat-card label="Storage used" :value="$fmtBytes($totalStorageBytes)" icon="hard-drive" hint="Across all accounts" />
    <x-studio.stat-card label="Completed revenue" :value="'RWF ' . number_format((float) $totalRevenue, 0)" icon="wallet"
        hint="All-time, completed payments" :href="route('admin.payments.index')" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Recent signups --}}
    <div class="card overflow-hidden lg:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent signups</h2>
                <p class="card-description">The newest accounts on the platform.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">View all <x-icon name="arrow-right" /></a>
        </div>
        @if($recentUsers->isEmpty())
            <div class="p-5"><x-ui.empty-state title="No users yet" description="New registrations will appear here." icon="users" /></div>
        @else
            <div class="table-wrap relative">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th class="hidden sm:table-cell">Status</th>
                            <th class="hidden md:table-cell text-right">Galleries</th>
                            <th class="text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUsers as $u)
                            <tr>
                                <td>
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-ui.avatar :name="$u->name" :src="$u->avatar_url ?? null" size="sm" />
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="truncate text-[13px] font-medium text-foreground">{{ $u->name }}</span>
                                                @if($u->role === 'admin')
                                                    <span class="badge badge-brand">Admin</span>
                                                @endif
                                            </div>
                                            <div class="truncate text-xs text-muted-foreground">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell">
                                    @if(! $u->is_active)
                                        <span class="badge badge-danger badge-dot">Suspended</span>
                                    @elseif($u->email_verified_at)
                                        <span class="badge badge-success badge-dot">Verified</span>
                                    @else
                                        <span class="badge badge-warning badge-dot">Unverified</span>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell text-right tabular-nums text-[13px]">{{ $u->galleries_count }}</td>
                                <td class="whitespace-nowrap text-right text-xs text-muted-foreground" title="{{ $u->created_at->format('M j, Y H:i') }}">{{ $u->created_at->diffForHumans(null, true) }} ago</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- System health --}}
    <div class="card flex flex-col">
        <div class="card-header">
            <div>
                <h2 class="card-title">System health</h2>
                <p class="card-description">Media processing pipeline.</p>
            </div>
            @if($health === 'healthy')
                <span class="badge badge-success badge-dot">Operational</span>
            @elseif($health === 'busy')
                <span class="badge badge-info badge-dot">Processing</span>
            @else
                <span class="badge badge-danger badge-dot">Failures today</span>
            @endif
        </div>
        <div class="card-body flex-1 space-y-4">
            <dl class="grid grid-cols-3 divide-x divide-border rounded-lg border border-border">
                <div class="px-3 py-3">
                    <dt class="text-xs text-muted-foreground">Processing</dt>
                    <dd class="mt-1 flex items-center gap-1.5 font-display text-xl font-semibold tabular-nums text-foreground">
                        {{ $processing }}
                        @if($processing > 0)<x-icon name="loader-circle" class="size-3.5 animate-spin text-info" />@endif
                    </dd>
                </div>
                <div class="px-3 py-3">
                    <dt class="text-xs text-muted-foreground">Queued</dt>
                    <dd class="mt-1 font-display text-xl font-semibold tabular-nums text-foreground">{{ $queued }}</dd>
                </div>
                <div class="px-3 py-3">
                    <dt class="text-xs text-muted-foreground">Failed today</dt>
                    <dd class="mt-1 font-display text-xl font-semibold tabular-nums {{ $failedToday > 0 ? 'text-destructive' : 'text-foreground' }}">{{ $failedToday }}</dd>
                </div>
            </dl>
            <ul class="space-y-2.5 text-[13px]">
                <li class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-muted-foreground"><x-icon name="shield" class="size-4" /> Galleries taken down</span>
                    <span class="font-medium tabular-nums text-foreground">{{ $takenDownGalleries ?? 0 }}</span>
                </li>
                <li class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-muted-foreground"><x-icon name="user-plus" class="size-4" /> New users, 30 days</span>
                    <span class="font-medium tabular-nums text-foreground">{{ $newUsers30d ?? 0 }}</span>
                </li>
            </ul>
        </div>
        <div class="border-t border-border px-5 py-3">
            <a href="{{ route('admin.jobs.index') }}" class="btn btn-secondary btn-sm w-full">
                <x-icon name="activity" /> Open processing queue
            </a>
        </div>
    </div>

    {{-- Recent payments --}}
    <div class="card overflow-hidden lg:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent payments</h2>
                <p class="card-description">Latest subscription and booking transactions.</p>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-ghost btn-sm">View all <x-icon name="arrow-right" /></a>
        </div>
        @if(($recentPayments ?? collect())->isEmpty())
            <div class="p-5"><x-ui.empty-state title="No payments yet" description="Transactions will show up here as soon as a studio pays for a plan or a client pays a deposit." icon="receipt" /></div>
        @else
            <div class="table-wrap relative">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th class="hidden sm:table-cell">Description</th>
                            <th>Status</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPayments as $p)
                            <tr>
                                <td>
                                    <div class="min-w-0">
                                        <div class="truncate text-[13px] font-medium text-foreground">{{ $p->user->name ?? 'Deleted user' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $p->created_at?->format('M j, H:i') }}</div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell text-[13px] text-muted-foreground">
                                    {{ $p->plan->name ?? \Illuminate\Support\Str::headline($p->purpose ?? 'Payment') }}
                                    @if($p->billing_cycle)<span class="text-subtle">· {{ ucfirst($p->billing_cycle) }}</span>@endif
                                </td>
                                <td><span class="badge badge-{{ $paymentVariant($p->status) }} badge-dot">{{ ucfirst($p->status) }}</span></td>
                                <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ $p->currency ?? 'RWF' }} {{ number_format((float) $p->amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Recent galleries --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent galleries</h2>
                <p class="card-description">Newest client deliveries.</p>
            </div>
            <a href="{{ route('admin.galleries.index') }}" class="btn btn-ghost btn-sm btn-icon" aria-label="View all galleries"><x-icon name="arrow-right" /></a>
        </div>
        @if($recentGalleries->isEmpty())
            <div class="p-5"><x-ui.empty-state title="No galleries yet" description="Galleries will appear as studios create them." icon="images" /></div>
        @else
            <ul class="divide-y divide-border">
                @foreach($recentGalleries as $g)
                    @php($cover = $g->getCoverUrl('sm'))
                    <li>
                        <a href="{{ route('admin.galleries.preview', $g->uuid) }}" class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted/40">
                            <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted text-muted-foreground">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="" class="size-full object-cover" loading="lazy" decoding="async" width="40" height="40">
                                @else
                                    <x-icon name="image" class="size-4" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-foreground">{{ $g->title }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ $g->user->name ?? 'Unknown' }} · {{ $g->photo_count }} photos</span>
                            </span>
                            @if($g->isTakenDown())
                                <span class="badge badge-danger">Taken down</span>
                            @elseif($g->visibility === 'public')
                                <span class="badge badge-success">Public</span>
                            @elseif($g->visibility === 'password')
                                <span class="badge badge-warning">PIN</span>
                            @else
                                <span class="badge badge-neutral">Private</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
