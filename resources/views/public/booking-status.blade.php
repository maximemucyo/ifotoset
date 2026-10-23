@extends('layouts.public', [
    'hideNav' => true,
    'seo' => null,
])

@php
    $status = $booking->status->value;
    $holdActive = (bool) $booking->hold_expires_at?->isFuture();
    $state = match (true) {
        $status === 'confirmed' || $status === 'completed' => 'confirmed',
        $status === 'cancelled' && $booking->deposit_status === 'expired' => 'expired',
        $status === 'cancelled' => 'cancelled',
        $booking->deposit_status === 'pending' && in_array($payment?->status, ['created', 'pending'], true) => 'paying',
        in_array($booking->deposit_status, ['pending', 'failed'], true) && $holdActive => 'failed',
        in_array($booking->deposit_status, ['pending', 'failed'], true) => 'expired',
        default => 'requested',
    };
    $bookUrl = route('subdomain.booking', ['username' => $photographer->username]);
    $money = fn ($amount) => $booking->currency . ' ' . number_format((float) $amount, 0);
@endphp

@section('content')
@include('public.partials.photographer-header', ['hasPackages' => true])

<section class="mx-auto max-w-xl px-4 py-12 sm:px-6 sm:py-16">
    <div class="card p-6 sm:p-8"
         @if($state === 'paying')
             x-data="{ poll: null, init() { this.poll = setInterval(() => this.check(), 4000); }, async check() { try { const r = await fetch(@js(route('subdomain.booking.status', ['username' => $photographer->username, 'booking' => (string) $booking->uuid])), { headers: { Accept: 'application/json' } }); const d = await r.json(); if (d.payment_status !== 'pending' && d.payment_status !== 'created') { clearInterval(this.poll); window.location.reload(); } } catch (e) {} } }"
         @endif>

        @if(session('error'))
            <div class="alert alert-danger mb-6"><x-icon name="circle-alert" /> <span>{{ session('error') }}</span></div>
        @endif

        @if($state === 'paying')
            <div class="flex flex-col items-center text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-muted"><x-icon name="smartphone" class="size-5" /></span>
                <h1 class="mt-5 pf-heading text-3xl text-foreground">Approve the payment on your phone</h1>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    We sent a Mobile Money request for <strong class="text-foreground">{{ $money($booking->deposit_amount) }}</strong> to {{ $payment->phone_number }}. Enter your PIN to confirm. This page updates by itself.
                </p>
                <p class="mt-4 inline-flex items-center gap-2 text-xs text-muted-foreground"><x-icon name="loader-circle" class="size-3.5 animate-spin" /> Waiting for confirmation…</p>
            </div>
        @elseif($state === 'failed')
            <div class="text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-destructive/10 text-destructive"><x-icon name="circle-x" class="size-5" /></span>
                <h1 class="mt-5 pf-heading text-3xl text-foreground">The deposit didn't go through</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ $payment?->failure_reason ?: 'The payment was declined or timed out.' }}
                    Your time is held until {{ $booking->hold_expires_at->copy()->setTimezone($booking->displayTimezone())->format('g:i A') }}. Try again, or use another number.
                </p>
            </div>
            <form method="POST" action="{{ route('subdomain.booking.pay', ['username' => $photographer->username, 'booking' => (string) $booking->uuid]) }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.input label="Mobile Money number" type="tel" name="payment_phone" required autocomplete="tel"
                            :value="old('payment_phone', $payment?->phone_number ?? $booking->client?->phone)" :error="$errors->first('payment_phone')" />
                <button type="submit" class="btn btn-primary w-full"><x-icon name="refresh-cw" /> Pay {{ $money($booking->deposit_amount) }} deposit</button>
            </form>
        @elseif($state === 'expired')
            <div class="text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-muted"><x-icon name="clock" class="size-5" /></span>
                <h1 class="mt-5 pf-heading text-3xl text-foreground">This booking wasn't completed</h1>
                <p class="mt-3 text-sm text-muted-foreground">The deposit wasn't paid in time, so the slot was released. You can pick a time again.</p>
                <a href="{{ $bookUrl }}" class="btn btn-primary mt-6">Choose a time</a>
            </div>
        @elseif($state === 'cancelled')
            <div class="text-center">
                <h1 class="pf-heading text-3xl text-foreground">This booking was cancelled</h1>
                <p class="mt-3 text-sm text-muted-foreground">Contact {{ $photographer->name }} at <a href="mailto:{{ $photographer->email }}" class="underline">{{ $photographer->email }}</a> if you have questions.</p>
            </div>
        @else
            <div class="text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-success/10 text-success"><x-icon name="circle-check" class="size-5" /></span>
                <h1 class="mt-5 pf-heading text-3xl text-foreground">{{ $state === 'confirmed' ? 'Your session is booked' : 'Request sent' }}</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    @if($state === 'confirmed')
                        @if($booking->isDepositPaid()) Your deposit was received. @endif
                        A confirmation is on its way to {{ $booking->client?->email }}.
                    @else
                        {{ $photographer->name }} will review your request and confirm by email at {{ $booking->client?->email }}.
                    @endif
                </p>
            </div>
        @endif

        <dl class="mt-8 space-y-3 border-t border-border pt-6 text-sm">
            @if($booking->package)
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Package</dt><dd class="text-right font-medium text-foreground">{{ $booking->package->name }}</dd></div>
            @endif
            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">When</dt><dd class="text-right font-medium text-foreground">{{ $booking->localStartsAt()->format('l, F j \a\t g:i A') }}</dd></div>
            @if($booking->location)
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Where</dt><dd class="text-right text-foreground">{{ $booking->location }}</dd></div>
            @endif
            @if($booking->price)
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Total</dt><dd class="text-right tabular-nums text-foreground">{{ $money($booking->price) }}</dd></div>
            @endif
            @if($booking->deposit_amount)
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Deposit</dt><dd class="text-right tabular-nums text-foreground">{{ $money($booking->deposit_amount) }}{{ $booking->isDepositPaid() ? ' · paid' : '' }}</dd></div>
                @if($booking->isDepositPaid() && $booking->balanceDue())
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Balance due</dt><dd class="text-right tabular-nums text-foreground">{{ $money($booking->balanceDue()) }}</dd></div>
                @endif
            @endif
        </dl>

        <div class="mt-8 text-center">
            <a href="{{ $photographer->public_url }}" class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground">Back to {{ $photographer->name }}'s portfolio</a>
        </div>
    </div>
</section>
@endsection

@section('footer')
    @include('public.partials.photographer-footer')
@endsection
