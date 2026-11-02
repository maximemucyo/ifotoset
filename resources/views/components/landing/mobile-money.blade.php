@props(['plans' => collect()])
@php
    // Plan names come from the Plan records, so admin changes show up here
    $depositPlans = $plans->filter(fn ($p) => in_array('booking_deposits', $p->featureCodes(), true))->pluck('name');
@endphp
{{-- The local differentiator gets the page's one dark band --}}
<section class="bg-kivu py-16 text-kivu-foreground sm:py-24">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        <div class="max-w-xl">
            <h2 class="headline">Clients pay the deposit from their phone.</h2>
            <p class="mt-5 text-[17px] leading-relaxed text-white/80">
                When a client books a session, they pay the deposit with MTN MoMo or Airtel Money right on your booking page. No card machine and no chasing transfers.
            </p>
            @if($depositPlans->isNotEmpty())
                <p class="mt-4 text-[15px] text-white/70">Included on the {{ $depositPlans->count() > 1 ? $depositPlans->slice(0, -1)->implode(', ') . ' and ' . $depositPlans->last() : $depositPlans->first() }} {{ Str::plural('plan', $depositPlans->count()) }}.</p>
            @endif
        </div>

        {{-- The payment step, at the size a client sees it --}}
        <div class="mx-auto w-full max-w-[340px] rounded-2xl bg-card p-6 text-foreground shadow-xl shadow-black/30" aria-hidden="true">
            <p class="text-[14px] text-muted-foreground">Pay Amani Studio</p>
            <p class="mt-1 font-headline text-[34px] font-semibold leading-none tabular-nums">RWF 50,000</p>
            <p class="mt-2 text-[14px] text-muted-foreground">Deposit for a portrait session</p>

            <div class="mt-6 grid grid-cols-2 gap-2">
                <span class="flex h-11 items-center justify-center rounded-lg border-2 border-brand bg-brand-soft text-[14px] font-medium">MTN MoMo</span>
                <span class="flex h-11 items-center justify-center rounded-lg border border-border text-[14px] text-muted-foreground">Airtel Money</span>
            </div>
            <p class="mt-4 text-[13px] font-medium">Phone number</p>
            <span class="mt-1.5 flex h-11 items-center rounded-lg border border-border-strong px-3 text-[15px] tabular-nums">078 412 3390</span>
            <span class="mt-5 flex h-12 items-center justify-center rounded-lg bg-primary text-[15px] font-medium text-primary-foreground">Pay RWF 50,000</span>
            <p class="mt-3 text-center text-[13px] text-muted-foreground">You'll confirm with your PIN on your phone.</p>
        </div>
    </div>
</section>
