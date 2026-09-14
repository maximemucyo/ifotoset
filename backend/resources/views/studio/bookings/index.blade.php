@extends('layouts.app', ['title' => 'Bookings - Studio'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Session Bookings</h1>
            <p class="text-xs text-muted-foreground mt-1">Review, approve, and manage client photography sessions.</p>
        </div>
        <a href="{{ route('studio.availability.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-card border border-border text-xs font-semibold text-foreground hover:border-primary transition-colors">
            Manage Availability &rarr;
        </a>
    </div>

    <!-- Status Filters -->
    @php $currentStatus = request('status', 'all'); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-border pb-4 text-xs font-medium">
        @foreach(['all' => 'All Bookings', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
            <a href="{{ route('studio.bookings.index', $key === 'all' ? [] : ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === $key ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Client</th>
                        <th class="px-6 py-4">Package</th>
                        <th class="px-6 py-4">Date &amp; Time</th>
                        <th class="px-6 py-4">Deposit</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-foreground">{{ $booking->client->name ?? 'Guest Client' }}</div>
                                <div class="text-xs text-muted-foreground">{{ $booking->client->email ?? '' }}</div>
                                @if($booking->client && $booking->client->phone)
                                    <div class="text-[11px] text-muted-foreground">{{ $booking->client->phone }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">{{ $booking->package->name ?? 'Custom Session' }}</div>
                                @if($booking->price)
                                    <div class="text-xs text-muted-foreground">{{ $booking->currency }} {{ number_format($booking->price, 0) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">{{ $booking->starts_at->format('M j, Y') }}</div>
                                <div class="text-xs text-muted-foreground">{{ $booking->starts_at->format('g:i A') }} - {{ $booking->ends_at->format('g:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($booking->deposit_paid)
                                    <span class="inline-flex items-center gap-1 font-semibold text-green-600">
                                        &check; Paid ({{ $booking->currency }} {{ number_format($booking->deposit_amount, 0) }})
                                    </span>
                                @elseif($booking->deposit_amount > 0)
                                    <span class="text-muted-foreground font-medium">
                                        Pending ({{ $booking->currency }} {{ number_format($booking->deposit_amount, 0) }})
                                    </span>
                                @else
                                    <span class="text-muted-foreground">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="$booking->status === 'confirmed' ? 'success' : ($booking->status === 'pending' ? 'warning' : ($booking->status === 'completed' ? 'default' : 'destructive'))">
                                    {{ ucfirst($booking->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($booking->status === 'pending')
                                        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-green-600 text-white hover:opacity-90 transition-opacity">
                                                Confirm
                                            </button>
                                        </form>
                                    @endif
                                    @if($booking->status === 'confirmed')
                                        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:opacity-90 transition-opacity">
                                                Mark Completed
                                            </button>
                                        </form>
                                    @endif
                                    @if($booking->status !== 'cancelled' && $booking->status !== 'completed')
                                        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="px-2.5 py-1 text-xs font-medium text-muted-foreground hover:text-destructive transition-colors" onclick="return confirm('Cancel this session booking?');">
                                                Cancel
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                No session bookings found in this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
