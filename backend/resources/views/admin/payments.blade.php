@extends('layouts.admin', ['title' => 'Financial Transactions - Admin'])

@section('content')
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Financial Transactions</h1>
            <p class="text-xs text-muted-foreground mt-1">Platform subscription payments, booking deposits, and PawaPay mobile money receipts.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Total Transactions: <span class="font-bold text-foreground">{{ $payments->total() }}</span>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-studio.stat-card label="Completed Revenue" :value="'RWF ' . number_format($totalCompleted, 0)" />
        <x-studio.stat-card label="Pending Processing" :value="'RWF ' . number_format($totalPending, 0)" />
        <x-studio.stat-card label="Total Transactions" :value="number_format($totalCount)" />
        <x-studio.stat-card label="Success Rate" :value="$successRate . '%'" />
    </div>

    <!-- Status Filters -->
    <div class="flex items-center gap-2 text-xs font-medium border-b border-border pb-4">
        <a href="{{ route('admin.payments.index') }}"
           class="px-3 py-1.5 rounded-lg {{ empty($status) ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
            All Transactions
        </a>
        <a href="{{ route('admin.payments.index', ['status' => 'completed']) }}"
           class="px-3 py-1.5 rounded-lg {{ $status === 'completed' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
            Completed
        </a>
        <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
           class="px-3 py-1.5 rounded-lg {{ $status === 'pending' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
            Pending
        </a>
        <a href="{{ route('admin.payments.index', ['status' => 'failed']) }}"
           class="px-3 py-1.5 rounded-lg {{ $status === 'failed' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
            Failed
        </a>
    </div>

    <!-- Transactions Table -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Reference</th>
                        <th class="px-6 py-4">Account / Customer</th>
                        <th class="px-6 py-4">Purpose</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Provider</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($payments as $tx)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-mono text-xs font-bold text-foreground">
                                    {{ substr($tx->uuid, 0, 8) }}...
                                </div>
                                @if($tx->pawapay_deposit_id)
                                    <div class="text-[10px] text-muted-foreground font-mono">PawaPay: {{ $tx->pawapay_deposit_id }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="font-semibold text-foreground">{{ $tx->user->name ?? 'Guest Client' }}</div>
                                <div class="text-muted-foreground">{{ $tx->phone_number ?? $tx->user->email ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($tx->purpose === 'plan_subscription')
                                    <div class="font-medium text-foreground">Plan: {{ $tx->plan->name ?? 'Pro Subscription' }}</div>
                                    <div class="text-[10px] uppercase font-bold text-muted-foreground">{{ $tx->billing_cycle ?? 'monthly' }}</div>
                                @elseif($tx->purpose === 'booking_deposit')
                                    <span class="font-medium text-foreground">Booking Deposit</span>
                                @else
                                    <span class="text-foreground capitalize">{{ str_replace('_', ' ', $tx->purpose) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-primary">
                                {{ $tx->currency }} {{ number_format($tx->amount, 0) }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ ucfirst($tx->provider ?? 'MTN MoMo') }}
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="$tx->status === 'completed' ? 'success' : ($tx->status === 'pending' ? 'warning' : 'destructive')">
                                    {{ ucfirst(str_replace('_', ' ', $tx->status)) }}
                                </x-ui.badge>
                                @if($tx->failure_reason)
                                    <div class="text-[10px] text-destructive max-w-[160px] truncate" title="{{ $tx->failure_reason }}">
                                        {{ $tx->failure_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $tx->created_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($tx->status === 'pending' && $tx->pawapay_deposit_id)
                                    <form method="POST" action="{{ route('admin.payments.sync', $tx->id) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-secondary hover:bg-secondary/80 text-foreground transition-colors border border-border shadow-sm">
                                            Sync Status
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted-foreground">&ndash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                                No financial transactions found in this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $payments->links() }}
    </div>
</div>
@endsection
