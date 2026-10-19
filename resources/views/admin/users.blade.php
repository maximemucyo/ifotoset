@extends('layouts.admin', ['title' => 'Users · Admin · ifotoset'])

@section('content')
@php
    $filters = [
        ['label' => 'All', 'params' => array_filter(['search' => $search]), 'active' => empty($role) && empty($verified)],
        ['label' => 'Photographers', 'params' => array_filter(['search' => $search, 'role' => 'user']), 'active' => $role === 'user'],
        ['label' => 'Admins', 'params' => array_filter(['search' => $search, 'role' => 'admin']), 'active' => $role === 'admin'],
        ['label' => 'Unverified', 'params' => array_filter(['search' => $search, 'verified' => 'unverified']), 'active' => $verified === 'unverified', 'dot' => true],
    ];
    $fmtBytes = function ($b) {
        $b = (float) $b; $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return ($i === 0 ? (int) $b : number_format($b, 1)) . ' ' . $u[$i];
    };
@endphp

<x-ui.page-header eyebrow="Admin console" title="Users" description="Every photographer, client and administrator registered on the platform.">
    <x-slot:meta>
        <p class="mt-3 text-xs text-muted-foreground"><span class="font-medium tabular-nums text-foreground">{{ number_format($users->total()) }}</span> {{ \Illuminate\Support\Str::plural('account', $users->total()) }}{{ $search || $role || $verified ? ' match these filters' : ' in total' }}</p>
    </x-slot:meta>
</x-ui.page-header>

<div class="card overflow-hidden">
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 border-b border-border p-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="tabs max-w-full overflow-x-auto" role="tablist" aria-label="Filter users">
            @foreach($filters as $f)
                <a href="{{ route('admin.users.index', $f['params']) }}" role="tab" aria-selected="{{ $f['active'] ? 'true' : 'false' }}" class="tab whitespace-nowrap">
                    @if(!empty($f['dot']))<span class="size-1.5 rounded-full bg-warning"></span>@endif
                    {{ $f['label'] }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="w-full sm:w-72">
            @if($role)<input type="hidden" name="role" value="{{ $role }}">@endif
            @if($verified)<input type="hidden" name="verified" value="{{ $verified }}">@endif
            <div class="input-group">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ $search }}" placeholder="Search name, email or username" aria-label="Search users" class="input input-sm pr-8">
                @if($search)
                    <a href="{{ route('admin.users.index', array_filter(['role' => $role, 'verified' => $verified])) }}" class="absolute right-2 flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Clear search">
                        <x-icon name="x" class="size-3.5" />
                    </a>
                @endif
            </div>
        </form>
    </div>

    @if($users->isEmpty())
        <div class="p-5">
            <x-ui.empty-state title="No users found" description="No accounts match this search or filter. Try a different term or clear the filters." icon="users">
                @if($search || $role || $verified)
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm mt-5"><x-icon name="rotate-ccw" /> Clear filters</a>
                @endif
            </x-ui.empty-state>
        </div>
    @else
        <div class="table-wrap relative">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="hidden md:table-cell">Role</th>
                        <th>Status</th>
                        <th class="hidden lg:table-cell">Plan</th>
                        <th class="hidden lg:table-cell text-right">Galleries</th>
                        <th class="hidden xl:table-cell text-right">Storage</th>
                        <th class="hidden md:table-cell">Joined</th>
                        <th class="w-px text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td class="max-w-[11rem] sm:max-w-none">
                                <button type="button" @click="$dispatch('open-user-drawer', { id: {{ $user->id }} })" class="group flex min-w-0 items-center gap-3 text-left">
                                    <x-ui.avatar :name="$user->name" :src="$user->avatar_url ?? null" size="sm" />
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-[13px] font-medium text-foreground group-hover:underline group-hover:underline-offset-4">{{ $user->name }}</span>
                                            @if($user->hasVerifiedEmail())
                                                <span class="text-success" title="Email verified on {{ $user->email_verified_at->format('M j, Y') }}"><x-icon name="circle-check" class="size-3.5" /><span class="sr-only">Verified</span></span>
                                            @endif
                                        </span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ $user->email }}</span>
                                    </span>
                                </button>
                            </td>
                            <td class="hidden md:table-cell">
                                @if($user->role === 'admin')
                                    <span class="badge badge-brand"><x-icon name="shield" /> Admin</span>
                                @else
                                    <span class="badge badge-neutral">{{ ucfirst($user->role ?? 'user') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if($user->is_active)
                                        <span class="badge badge-success badge-dot">Active</span>
                                    @else
                                        <span class="badge badge-danger badge-dot">Suspended</span>
                                    @endif
                                    @unless($user->hasVerifiedEmail())
                                        <span class="badge badge-warning" title="Email unverified">Unverified</span>
                                    @endunless
                                </div>
                            </td>
                            <td class="hidden lg:table-cell text-[13px]">{{ ucfirst($user->plan->name ?? 'Free') }}</td>
                            <td class="hidden lg:table-cell text-right text-[13px] tabular-nums">{{ $user->galleries_count }}</td>
                            <td class="hidden xl:table-cell text-right text-[13px] tabular-nums text-muted-foreground">{{ $fmtBytes($user->storage_used_bytes) }}</td>
                            <td class="hidden md:table-cell whitespace-nowrap text-[13px] text-muted-foreground">{{ $user->created_at->format('M j, Y') }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1"
                                     x-data="{ planModalOpen: false, revokeModalOpen: false, menuOpen: false, menuTop: 0, menuLeft: 0,
                                               openMenu() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuTop = r.bottom + 6; this.menuLeft = Math.max(8, r.right - 224); this.menuOpen = !this.menuOpen; } }"
                                     @keydown.escape.window="menuOpen = false; planModalOpen = false; revokeModalOpen = false">
                                    <button type="button"
                                            @click="$dispatch('open-user-drawer', { id: {{ $user->id }} })"
                                            class="btn btn-outline btn-xs hidden sm:inline-flex">
                                        <x-icon name="eye" /> Inspect
                                    </button>

                                    <button type="button" x-ref="trigger" @click="openMenu()" class="btn btn-ghost btn-xs btn-icon" :class="menuOpen && 'bg-muted text-foreground'" aria-label="More actions for {{ $user->name }}" aria-haspopup="menu" :aria-expanded="menuOpen">
                                        <x-icon name="ellipsis" />
                                    </button>

                                    {{-- Row actions menu (fixed so it escapes the scrolling table) --}}
                                    <template x-teleport="body">
                                        <div x-show="menuOpen" x-cloak
                                             @click.outside="if (! $refs.trigger.contains($event.target)) menuOpen = false"
                                             @scroll.window.capture="menuOpen = false" @resize.window="menuOpen = false"
                                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                             :style="`top:${menuTop}px;left:${menuLeft}px`"
                                             class="menu fixed w-56 origin-top-right text-left" role="menu">
                                            <div class="menu-label truncate">{{ $user->name }}</div>
                                            <button type="button" class="menu-item" role="menuitem" @click="menuOpen = false; $dispatch('open-user-drawer', { id: {{ $user->id }} })">
                                                <x-icon name="eye" /> Inspect account
                                            </button>
                                            @if($user->username)
                                                <a href="{{ $user->public_url }}" target="_blank" rel="noopener" class="menu-item" role="menuitem">
                                                    <x-icon name="globe" /> Public portfolio <x-icon name="arrow-up-right" class="ml-auto !size-3.5" />
                                                </a>
                                            @endif
                                            @if(auth()->id() !== $user->id && $user->role !== 'admin' && $user->is_active)
                                                <form method="POST" action="{{ route('admin.users.impersonate', $user->id) }}">
                                                    @csrf
                                                    <button type="submit" class="menu-item" role="menuitem" title="Switch into user view">
                                                        <x-icon name="log-in" /> Impersonate
                                                    </button>
                                                </form>
                                            @endif
                                            <div class="menu-separator"></div>
                                            <button type="button" class="menu-item" role="menuitem" @click="menuOpen = false; planModalOpen = true">
                                                <x-icon name="credit-card" /> Change plan
                                            </button>
                                            @if($user->plan && $user->plan->slug !== 'free')
                                                <button type="button" class="menu-item" role="menuitem" @click="menuOpen = false; revokeModalOpen = true">
                                                    <x-icon name="rotate-ccw" /> Revoke paid plan
                                                </button>
                                            @endif
                                            @if(auth()->id() !== $user->id)
                                                <div class="menu-separator"></div>
                                                <form method="POST" action="{{ route('admin.users.status', $user->id) }}" onsubmit="return confirm('{{ $user->is_active ? 'Suspend this account?' : 'Activate this account?' }}');">
                                                    @csrf
                                                    @if($user->is_active)
                                                        <button type="submit" class="menu-item menu-item-danger" role="menuitem"><x-icon name="ban" /> Suspend account</button>
                                                    @else
                                                        <button type="submit" class="menu-item" role="menuitem"><x-icon name="circle-check" /> Activate account</button>
                                                    @endif
                                                </form>
                                            @endif
                                        </div>
                                    </template>

                                    {{-- Change plan modal --}}
                                    <template x-teleport="body">
                                        <div x-show="planModalOpen" x-cloak
                                             class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
                                             role="dialog" aria-modal="true">
                                            <div x-show="planModalOpen" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="planModalOpen = false"></div>
                                            <div x-show="planModalOpen"
                                                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                                 class="relative w-full max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-card text-left shadow-2xl">
                                                <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                                                    <div>
                                                        <h3 class="text-[15px] font-semibold text-foreground">Change plan</h3>
                                                        <p class="mt-0.5 text-[13px] text-muted-foreground">{{ $user->name }} · currently <span class="font-medium text-foreground">{{ $user->plan->name ?? 'Free' }}</span>, {{ $fmtBytes($user->storage_used_bytes) }} used</p>
                                                    </div>
                                                    <button type="button" @click="planModalOpen = false" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close"><x-icon name="x" /></button>
                                                </div>

                                                <form method="POST" action="{{ route('admin.users.plan', $user->id) }}">
                                                    @csrf
                                                    <div class="space-y-4 px-5 py-4">
                                                        <div>
                                                            <label class="label" for="plan-{{ $user->id }}">New plan</label>
                                                            <select id="plan-{{ $user->id }}" name="plan_slug" class="select">
                                                                @foreach($plans as $p)
                                                                    @php
                                                                        $pLimit = $p->storage_limit;
                                                                        $pGb = ($pLimit % 1000000000 === 0 && ($pLimit % (1024 * 1024) !== 0))
                                                                            ? round($pLimit / 1000000000, 0)
                                                                            : round($pLimit / (1024 * 1024 * 1024), 0);
                                                                    @endphp
                                                                    <option value="{{ $p->slug }}" {{ $user->plan_id === $p->id ? 'selected' : '' }}>
                                                                        {{ $p->name }} ({{ $pGb >= 1000 ? round($pGb / 1000, 1) . ' TB' : $pGb . ' GB' }}, Unlimited Galleries)
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="label" for="cycle-{{ $user->id }}">Billing cycle</label>
                                                            <select id="cycle-{{ $user->id }}" name="billing_cycle" class="select">
                                                                <option value="monthly">Monthly</option>
                                                                <option value="annual">Annual (12 Months)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="label" for="reason-{{ $user->id }}">Reason <span class="font-normal text-muted-foreground">(audit log)</span></label>
                                                            <input id="reason-{{ $user->id }}" type="text" name="reason" placeholder="e.g. VIP promotion / manual override" class="input">
                                                        </div>
                                                    </div>
                                                    <div class="card-footer">
                                                        <button type="button" @click="planModalOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">Apply plan change</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Revoke plan modal --}}
                                    <template x-teleport="body">
                                        <div x-show="revokeModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
                                            <div x-show="revokeModalOpen" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="revokeModalOpen = false"></div>
                                            <div x-show="revokeModalOpen" x-transition class="relative w-full max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-card text-left shadow-2xl">
                                                <form method="POST" action="{{ route('admin.users.plan.revoke', $user->id) }}">
                                                    @csrf
                                                    <div class="space-y-4 px-5 py-5">
                                                        <div class="flex gap-3">
                                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"><x-icon name="triangle-alert" class="size-4" /></span>
                                                            <div>
                                                                <h3 class="text-[15px] font-semibold text-foreground">Revoke paid plan?</h3>
                                                                <p class="mt-1 text-[13px] text-muted-foreground">{{ $user->name }} will be moved back to the Free tier immediately.</p>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="label" for="revoke-reason-{{ $user->id }}">Reason <span class="font-normal text-muted-foreground">(optional)</span></label>
                                                            <input id="revoke-reason-{{ $user->id }}" type="text" name="reason" maxlength="255" class="input" placeholder="e.g. Refund issued">
                                                        </div>
                                                    </div>
                                                    <div class="card-footer">
                                                        <button type="button" @click="revokeModalOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                                                        <button type="submit" class="btn btn-danger btn-sm">Revoke plan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($users->hasPages())
        <div class="border-t border-border px-4 py-3">
            {{ $users->links() }}
        </div>
    @endif
</div>

{{-- Slide-over user detail drawer --}}
@include('admin.users.partials.user-detail-drawer')

@endsection
