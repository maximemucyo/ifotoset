{{-- Deposit / balance line under a booking's price. Vars: $booking --}}
@if($booking->isDepositPaid())
    <div class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-success"><x-icon name="check" class="size-3" /> Deposit {{ number_format((float) $booking->deposit_amount, 0) }} paid</div>
    @if($booking->balanceDue())
        <div class="text-[11px] tabular-nums text-muted-foreground">Balance {{ number_format($booking->balanceDue(), 0) }} {{ $booking->currency }}</div>
    @endif
    @if($booking->status->value === 'cancelled')
        <div class="text-[11px] font-medium text-destructive">Refund or rebook</div>
    @endif
@elseif($booking->isAwaitingDeposit())
    <div class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-warning"><x-icon name="clock" class="size-3" /> {{ $booking->deposit_status === 'failed' ? 'Deposit failed, client retrying' : 'Awaiting deposit' }}</div>
@endif
