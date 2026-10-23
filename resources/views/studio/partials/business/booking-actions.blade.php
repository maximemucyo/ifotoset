{{-- Inline status actions for a booking. Vars: $booking, $status (string), $compact (bool) --}}
@php $compact = $compact ?? false; @endphp
<div class="flex items-center gap-1.5 {{ $compact ? '' : 'justify-end' }}">
    @if($status === 'pending')
        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="confirmed">
            <button type="submit" class="btn btn-primary btn-xs">
                <x-icon name="check" /> Confirm
            </button>
        </form>
    @endif
    @if($status === 'confirmed')
        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-outline btn-xs">
                <x-icon name="check-check" /> Mark completed
            </button>
        </form>
    @endif
    @if($status !== 'cancelled' && $status !== 'completed')
        <form method="POST" action="{{ route('studio.bookings.status', $booking->uuid) }}"
              @submit.prevent="$dispatch('confirm-action', { form: $el, title: 'Cancel this booking?', message: @js('The session with ' . ($booking->client->name ?? 'this client') . ' on ' . $booking->localStartsAt()->format('M j') . ' will be marked as cancelled and the time slot freed up.'), confirm: 'Cancel booking', cancel: 'Keep booking' })">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="btn btn-ghost btn-xs {{ $compact ? '' : 'btn-icon size-7' }} text-muted-foreground hover:text-destructive" title="Cancel booking">
                <x-icon name="x" /> <span class="{{ $compact ? '' : 'sr-only' }}">Cancel</span>
            </button>
        </form>
    @endif
    @if($status === 'completed' || $status === 'cancelled')
        <span class="text-xs text-subtle">No actions</span>
    @endif
</div>
