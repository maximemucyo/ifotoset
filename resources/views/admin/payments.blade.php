@extends('layouts.admin', ['title' => 'Payments · Admin'])

@section('content')
@php
    $statusTabs = [
        ''          => ['label' => 'All', 'dot' => null],
        'completed' => ['label' => 'Completed', 'dot' => 'bg-success'],
        'pending'   => ['label' => 'Pending', 'dot' => 'bg-warning'],
        'failed'    => ['label' => 'Failed', 'dot' => 'bg-destructive'],
    ];
    $statusVariant = fn ($s) => match ($s) {
        'completed' => 'success',
        'pending' => 'warning',
        'failed', 'cancelled', 'rejected' => 'destructive',
        default => 'neutral',
    };
    $providerLabel = function ($tx) {
        $code = strtoupper((string) ($tx->provider ?? ''));
        return match (true) {
            $code === '' => 'MTN MoMo',
            str_contains($code, 'MTN') => 'MTN MoMo',
            str_contains($code, 'AIRTEL') => 'Airtel Money',
            default => ucwords(strtolower(str_replace('_', ' ', $code))),
        };
    };
    $purposeLabel = function ($tx) {
        return match ($tx->purpose) {
            'plan_subscription' => 'Plan · ' . ($tx->plan->name ?? 'Subscription'),
            'booking_deposit' => 'Booking deposit',
            default => ucfirst(str_replace('_', ' ', (string) $tx->purpose)),
        };
    };
@endphp

<x-ui.page-header title="Payments" description="Subscription payments, booking deposits and mobile money receipts across the platform." />

{{-- KPI row --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-studio.stat-card label="Completed revenue" :value="'RWF ' . number_format($totalCompleted, 0)" icon="banknote" hint="Settled payments" />
    <x-studio.stat-card label="Pending" :value="'RWF ' . number_format($totalPending, 0)" icon="clock" hint="Awaiting confirmation" />
    <x-studio.stat-card label="Transactions" :value="number_format($totalCount)" icon="receipt" hint="All time" />
    <x-studio.stat-card label="Success rate" :value="$totalCount > 0 ? $successRate . '%' : '—'" icon="percent" :hint="$totalCount > 0 ? null : 'No data yet'">
        @if($totalCount > 0)<div class="progress mt-3"><span style="width: {{ min(100, max(0, (float) $successRate)) }}%" class="bg-success"></span></div>@endif
    </x-studio.stat-card>
</div>

{{-- Transactions --}}
<div class="card mt-6 overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <nav class="tabs max-w-full overflow-x-auto scrollbar-none" aria-label="Filter by status">
            @foreach($statusTabs as $key => $tab)
                @php($isActive = ($key === '' && empty($status)) || $status === $key)
                <a href="{{ $key === '' ? route('admin.payments.index') : route('admin.payments.index', ['status' => $key]) }}"
                   class="tab whitespace-nowrap {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                    @if($tab['dot'])<span class="size-1.5 rounded-full {{ $tab['dot'] }}"></span>@endif
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
        <p class="text-xs text-muted-foreground">
            @if($payments->total() > 0)
                Showing <span class="tabular-nums text-foreground">{{ $payments->firstItem() }}–{{ $payments->lastItem() }}</span> of <span class="tabular-nums text-foreground">{{ number_format($payments->total()) }}</span>
            @else
                No results
            @endif
        </p>
    </div>

    @if($payments->isEmpty())
        <div class="p-4 sm:p-5">
            <x-ui.empty-state
                icon="credit-card"
                :title="empty($status) ? 'No transactions yet' : 'No ' . $status . ' transactions'"
                :description="empty($status) ? 'Payments appear here as soon as photographers subscribe or clients pay a booking deposit.' : 'Nothing matches this filter right now. Try another status.'" />
        </div>
    @else
        {{-- Desktop table --}}
        <div class="table-wrap relative hidden md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Purpose</th>
                        <th class="text-right">Amount</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $tx)
                        <tr>
                            <td>
                                <button type="button" @click="$clipboard(@js((string) $tx->uuid), 'Reference copied')"
                                        class="group inline-flex items-center gap-1.5 rounded-md font-mono text-xs font-medium text-foreground transition hover:text-brand"
                                        title="Copy full reference">
                                    {{ substr((string) $tx->uuid, 0, 8) }}
                                    <x-icon name="copy" class="size-3 text-subtle opacity-0 transition group-hover:opacity-100" />
                                </button>
                            </td>
                            <td>
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <x-ui.avatar :name="$tx->user->name ?? 'Guest'" size="sm" />
                                    <div class="min-w-0">
                                        <div class="max-w-[180px] truncate text-[13px] font-medium text-foreground">{{ $tx->user->name ?? 'Guest client' }}</div>
                                        <div class="max-w-[180px] truncate text-xs text-muted-foreground">{{ $tx->phone_number ?? $tx->user->email ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-[13px] text-foreground">{{ $purposeLabel($tx) }}</div>
                                @if($tx->purpose === 'plan_subscription')
                                    <div class="text-xs capitalize text-muted-foreground">{{ $tx->billing_cycle ?? 'monthly' }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <span class="text-xs text-muted-foreground">{{ $tx->currency }}</span>
                                <span class="font-semibold tabular-nums text-foreground">{{ number_format($tx->amount, 0) }}</span>
                            </td>
                            <td>
                                <div class="text-[13px] text-foreground" @if($tx->provider) title="{{ $tx->provider }}" @endif>{{ $providerLabel($tx) }}</div>
                                @if($tx->pawapay_deposit_id)
                                    <button type="button" @click="$clipboard(@js($tx->pawapay_deposit_id), 'Deposit ID copied')"
                                            class="max-w-[160px] truncate font-mono text-[11px] text-muted-foreground transition hover:text-foreground"
                                            title="PawaPay deposit {{ $tx->pawapay_deposit_id }}">
                                        {{ $tx->pawapay_deposit_id }}
                                    </button>
                                @endif
                            </td>
                            <td>
                                <x-ui.badge :variant="$statusVariant($tx->status)" dot>{{ ucfirst(str_replace('_', ' ', $tx->status)) }}</x-ui.badge>
                                @if($tx->failure_reason)
                                    <div class="mt-1 max-w-[180px] truncate text-[11px] text-destructive" title="{{ $tx->failure_reason }}">{{ $tx->failure_reason }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="text-[13px] text-foreground">{{ $tx->created_at->format('M j, Y') }}</div>
                                <div class="text-xs tabular-nums text-muted-foreground">{{ $tx->created_at->format('g:i A') }}</div>
                            </td>
                            <td class="text-right">
                                @if($tx->status === 'pending' && $tx->pawapay_deposit_id)
                                    <form method="POST" action="{{ route('admin.payments.sync', $tx->id) }}" class="inline-block" x-data="{ busy: false }" @submit="busy = true">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-xs" :disabled="busy">
                                            <x-icon name="refresh-cw" x-bind:class="busy && 'animate-spin'" />
                                            <span x-text="busy ? 'Syncing…' : 'Sync'">Sync</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-subtle">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile list --}}
        <ul class="divide-y divide-border md:hidden">
            @foreach($payments as $tx)
                <li class="px-4 py-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-foreground">{{ $tx->user->name ?? 'Guest client' }}</div>
                            <div class="mt-0.5 truncate text-xs text-muted-foreground">{{ $purposeLabel($tx) }} · {{ $providerLabel($tx) }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-sm font-semibold tabular-nums text-foreground">{{ $tx->currency }} {{ number_format($tx->amount, 0) }}</div>
                            <x-ui.badge :variant="$statusVariant($tx->status)" dot class="mt-1">{{ ucfirst(str_replace('_', ' ', $tx->status)) }}</x-ui.badge>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                        <span class="truncate font-mono">{{ substr((string) $tx->uuid, 0, 8) }}@if($tx->pawapay_deposit_id) · {{ \Illuminate\Support\Str::limit($tx->pawapay_deposit_id, 14) }}@endif</span>
                        <span class="shrink-0 tabular-nums">{{ $tx->created_at->format('M j, g:i A') }}</span>
                    </div>
                    @if($tx->failure_reason)
                        <p class="mt-1.5 text-xs text-destructive">{{ $tx->failure_reason }}</p>
                    @endif
                    @if($tx->status === 'pending' && $tx->pawapay_deposit_id)
                        <form method="POST" action="{{ route('admin.payments.sync', $tx->id) }}" class="mt-2.5" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-xs w-full" :disabled="busy">
                                <x-icon name="refresh-cw" x-bind:class="busy && 'animate-spin'" />
                                <span x-text="busy ? 'Syncing…' : 'Sync with PawaPay'">Sync with PawaPay</span>
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if($payments->hasPages())
        <div class="border-t border-border px-4 py-3 sm:px-5">
            {{ $payments->links() }}
        </div>
    @endif
</div>
@endsection
