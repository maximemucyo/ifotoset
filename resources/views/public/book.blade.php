@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
])

@php
    $hasPackages = $packages->isNotEmpty();
    $packageData = $packages->map(function ($pkg) use ($depositsEnabled) {
        $deposit = $depositsEnabled ? $pkg->computedDepositAmount() : null;
        $mins = (int) $pkg->duration_minutes;
        return [
            'uuid' => (string) $pkg->uuid,
            'name' => $pkg->name,
            'price' => number_format((float) $pkg->price, 0),
            'currency' => $pkg->currency,
            'duration' => $mins >= 60 && $mins % 60 === 0 ? ($mins / 60) . ' ' . Str::plural('hour', $mins / 60) : $mins . ' min',
            'deposit' => $deposit > 0 ? number_format($deposit, 0) : null,
        ];
    })->values();
    $tzLabel = str_replace(['_', '/'], [' ', ' / '], $timezone);
    $hasOld = filled(old('client_name'));
    $initialStep = $hasOld ? (session('error') ? 2 : 3) : ($selectedPackage ? 2 : 1);
@endphp

@section('content')
@include('public.partials.photographer-header', ['hasPackages' => $hasPackages, 'current' => 'book'])

<section class="relative">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <div class="text-center">
            <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Booking</p>
            <h1 class="pf-heading mt-3 text-4xl leading-[1.05] text-foreground sm:text-5xl">Book a session with {{ $photographer->name }}</h1>
        </div>

        @if(!$hasPackages)
            <x-ui.empty-state class="mt-10" icon="calendar" title="Not taking online bookings yet"
                description="{{ $photographer->name }} hasn't published any packages. Check back soon." />
        @else
            <div class="mt-10" x-data="bookingFlow({
                    packages: @js($packageData),
                    selected: @js($selectedPackage),
                    step: {{ $initialStep }},
                    startsAt: @js(old('starts_at')),
                    timezone: @js($timezone),
                    daysUrl: @js(route('subdomain.booking.availability', ['username' => $photographer->username])),
                    slotsUrl: @js(route('subdomain.booking.slots', ['username' => $photographer->username])),
                 })">

                {{-- Progress --}}
                <ol class="mb-8 flex items-center justify-center gap-2 text-xs sm:gap-3">
                    @foreach(['Package', 'Date & time', 'Your details'] as $i => $label)
                        <li class="flex items-center gap-2 sm:gap-3">
                            <span class="flex items-center gap-2" :class="step >= {{ $i + 1 }} ? 'text-foreground' : 'text-muted-foreground'">
                                <span class="flex size-6 items-center justify-center rounded-full border text-[11px] font-medium"
                                      :class="step > {{ $i + 1 }} ? 'border-foreground bg-foreground text-background' : (step === {{ $i + 1 }} ? 'border-foreground' : 'border-border')">{{ $i + 1 }}</span>
                                <span class="hidden sm:inline">{{ $label }}</span>
                            </span>
                            @if($i < 2)<span class="h-px w-6 bg-border sm:w-10"></span>@endif
                        </li>
                    @endforeach
                </ol>

                @if(session('error'))
                    <div class="alert alert-danger mb-6"><x-icon name="circle-alert" /> <span>{{ session('error') }}</span></div>
                @endif

                {{-- Step 1: package --}}
                <div x-show="step === 1" x-cloak class="grid gap-3">
                    <template x-for="pkg in packages" :key="pkg.uuid">
                        <button type="button" @click="choosePackage(pkg.uuid)"
                                class="card card-hover flex items-center justify-between gap-4 p-5 text-left"
                                :class="selected === pkg.uuid ? 'ring-2 ring-foreground' : ''">
                            <span class="min-w-0">
                                <span class="pf-heading block text-xl text-foreground" x-text="pkg.name"></span>
                                <span class="mt-1 block text-xs text-muted-foreground">
                                    <span x-text="pkg.duration"></span>
                                    <template x-if="pkg.deposit"><span> · <span x-text="pkg.currency + ' ' + pkg.deposit"></span> deposit by Mobile Money</span></template>
                                </span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xs text-muted-foreground" x-text="pkg.currency"></span>
                                <span class="font-display text-2xl font-semibold tabular-nums text-foreground" x-text="pkg.price"></span>
                            </span>
                        </button>
                    </template>
                </div>

                {{-- Step 2: date & time --}}
                <div x-show="step === 2" x-cloak class="card p-5 sm:p-7">
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="step = 1" class="btn btn-ghost btn-sm -ml-2"><x-icon name="arrow-left" /> <span x-text="current()?.name ?? 'Package'"></span></button>
                        <span class="text-xs text-muted-foreground">Times in {{ $tzLabel }}</span>
                    </div>

                    <div class="mt-5 grid gap-8 md:grid-cols-[1fr_14rem]">
                        <div>
                            <div class="flex items-center justify-between">
                                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="shiftMonth(-1)" :disabled="!canGoBack()" aria-label="Previous month"><x-icon name="chevron-left" /></button>
                                <p class="text-sm font-medium text-foreground" x-text="monthLabel()"></p>
                                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="shiftMonth(1)" aria-label="Next month"><x-icon name="chevron-right" /></button>
                            </div>
                            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] uppercase tracking-wider text-muted-foreground">
                                <template x-for="d in ['Mo','Tu','We','Th','Fr','Sa','Su']"><span x-text="d"></span></template>
                            </div>
                            <div class="mt-1 grid grid-cols-7 gap-1" :class="loadingDays ? 'opacity-50' : ''">
                                <template x-for="cell in calendar()" :key="cell.key">
                                    <button type="button" class="aspect-square rounded-lg text-sm tabular-nums transition"
                                            :disabled="!cell.available"
                                            @click="pickDate(cell.date)"
                                            :class="!cell.date ? 'invisible' : (date === cell.date ? 'bg-foreground text-background' : (cell.available ? 'bg-muted font-medium text-foreground hover:bg-secondary' : 'text-subtle line-through decoration-transparent cursor-not-allowed'))"
                                            x-text="cell.day"></button>
                                </template>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground" x-show="!loadingDays && availableDays.length === 0">No open dates this month. Try the next one.</p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-foreground" x-text="date ? dateLabel(date) : 'Pick a date'"></p>
                            <div class="mt-3 grid max-h-80 grid-cols-2 gap-2 overflow-y-auto md:grid-cols-1">
                                <template x-for="slot in slots" :key="slot.starts_at">
                                    <button type="button" class="btn btn-outline btn-sm justify-center tabular-nums"
                                            :class="startsAt === slot.starts_at ? 'btn-primary' : ''"
                                            @click="pickSlot(slot.starts_at)" x-text="timeLabel(slot.starts_at)"></button>
                                </template>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground" x-show="loadingSlots">Loading times…</p>
                            <p class="mt-2 text-xs text-muted-foreground" x-show="date && !loadingSlots && slots.length === 0">No open times left on this day.</p>
                        </div>
                    </div>
                </div>

                {{-- Step 3: details --}}
                <form x-show="step === 3" x-cloak method="POST" action="{{ route('subdomain.photographer.book', ['username' => $photographer->username]) }}" class="card p-5 sm:p-7" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="package_id" :value="selected">
                    <input type="hidden" name="starts_at" :value="startsAt">
                    {{-- Honeypot for bots; hidden from people and assistive tech --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="booking_website">Website</label>
                        <input type="text" id="booking_website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/60 px-4 py-3 text-sm">
                        <span class="min-w-0">
                            <span class="font-medium text-foreground" x-text="current()?.name"></span>
                            <span class="text-muted-foreground"> · </span>
                            <span class="text-muted-foreground" x-text="startsAt ? dateLabel(startsAt.slice(0, 10)) + ', ' + timeLabel(startsAt) : ''"></span>
                        </span>
                        <button type="button" @click="step = 2" class="text-xs font-medium text-foreground underline underline-offset-2">Change</button>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-ui.input label="Full name" name="client_name" required autocomplete="name" placeholder="Jane Smith"
                                    :value="old('client_name')" :error="$errors->first('client_name')" />
                        <x-ui.input label="Email" type="email" name="client_email" required autocomplete="email" placeholder="jane@example.com"
                                    :value="old('client_email')" :error="$errors->first('client_email')" />
                        <x-ui.input label="Phone / WhatsApp" type="tel" name="client_phone" required autocomplete="tel" placeholder="+250 788 000 000"
                                    :value="old('client_phone')" :error="$errors->first('client_phone')" />
                        <x-ui.input label="Session location" name="location" placeholder="Studio, venue or address" hint="Optional"
                                    :value="old('location')" :error="$errors->first('location')" />
                        <div class="sm:col-span-2">
                            <x-ui.textarea label="Tell us about your session" name="notes" id="notes" rows="4"
                                           placeholder="The occasion, the mood you're after, anything special…"
                                           :value="old('notes')" :error="$errors->first('notes')" />
                        </div>
                        <template x-if="current()?.deposit">
                            <div class="sm:col-span-2 rounded-lg border border-border p-4">
                                <p class="text-sm font-medium text-foreground">
                                    Deposit due now: <span x-text="current().currency + ' ' + current().deposit"></span>
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">You'll get a Mobile Money prompt on your phone to approve it. Your time is held for {{ \App\Models\Booking::DEPOSIT_HOLD_MINUTES }} minutes while you pay.</p>
                                <div class="mt-4">
                                    <x-ui.input label="Mobile Money number" type="tel" name="payment_phone" autocomplete="tel" placeholder="078 000 0000"
                                                hint="Leave blank to use your phone number above."
                                                :value="old('payment_phone')" :error="$errors->first('payment_phone')" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-7 flex flex-col-reverse items-stretch gap-3 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted-foreground" x-text="current()?.deposit ? 'Only the deposit is charged now.' : 'No payment is taken now.'"></p>
                        <button type="submit" class="btn btn-primary btn-lg" :disabled="submitting || !startsAt">
                            <span x-text="current()?.deposit ? 'Book & pay deposit' : 'Send request'"></span> <x-icon name="send" />
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</section>
@endsection

@section('footer')
    @include('public.partials.photographer-footer')
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bookingFlow', (opts) => ({
            packages: opts.packages,
            selected: opts.packages.some(p => p.uuid === opts.selected) ? opts.selected : null,
            step: opts.packages.some(p => p.uuid === opts.selected) ? opts.step : 1,
            startsAt: opts.startsAt || null,
            timezone: opts.timezone,
            month: null,
            availableDays: [],
            loadingDays: false,
            date: null,
            slots: [],
            loadingSlots: false,
            submitting: false,

            init() {
                const today = this.todayInTz();
                this.month = (this.startsAt ? this.startsAt.slice(0, 7) : today.slice(0, 7));
                if (this.startsAt) this.date = this.startsAt.slice(0, 10);
                if (this.step === 3 && !this.startsAt) this.step = 2;
                if (this.selected) {
                    this.loadDays();
                    if (this.date) this.loadSlots();
                }
            },
            current() { return this.packages.find(p => p.uuid === this.selected); },
            choosePackage(uuid) {
                if (this.selected !== uuid) { this.date = null; this.slots = []; this.startsAt = null; }
                this.selected = uuid;
                this.step = 2;
                this.loadDays();
            },
            todayInTz() {
                return new Intl.DateTimeFormat('en-CA', { timeZone: this.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
            },
            canGoBack() { return this.month > this.todayInTz().slice(0, 7); },
            shiftMonth(delta) {
                let [y, m] = this.month.split('-').map(Number);
                m += delta;
                if (m < 1) { m = 12; y--; } else if (m > 12) { m = 1; y++; }
                this.month = `${y}-${String(m).padStart(2, '0')}`;
                this.loadDays();
            },
            monthLabel() {
                const [y, m] = this.month.split('-').map(Number);
                return new Date(Date.UTC(y, m - 1, 15)).toLocaleDateString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' });
            },
            calendar() {
                const [y, m] = this.month.split('-').map(Number);
                const first = new Date(Date.UTC(y, m - 1, 1)).getUTCDay();
                const lead = (first + 6) % 7;
                const days = new Date(Date.UTC(y, m, 0)).getUTCDate();
                const cells = [];
                for (let i = 0; i < lead; i++) cells.push({ key: 'b' + i, date: null, day: '', available: false });
                for (let d = 1; d <= days; d++) {
                    const date = `${this.month}-${String(d).padStart(2, '0')}`;
                    cells.push({ key: date, date, day: d, available: this.availableDays.includes(date) });
                }
                return cells;
            },
            async loadDays() {
                if (!this.selected) return;
                this.loadingDays = true;
                const month = this.month;
                try {
                    const res = await fetch(`${opts.daysUrl}?package=${encodeURIComponent(this.selected)}&month=${month}`, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (month === this.month) this.availableDays = data.days || [];
                } catch (e) {
                    this.availableDays = [];
                } finally {
                    this.loadingDays = false;
                }
            },
            async pickDate(date) {
                this.date = date;
                this.startsAt = null;
                await this.loadSlots();
            },
            async loadSlots() {
                this.loadingSlots = true;
                this.slots = [];
                try {
                    const res = await fetch(`${opts.slotsUrl}?package=${encodeURIComponent(this.selected)}&date=${this.date}`, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    this.slots = data.slots || [];
                } catch (e) {
                    this.slots = [];
                } finally {
                    this.loadingSlots = false;
                }
            },
            pickSlot(startsAt) {
                this.startsAt = startsAt;
                this.step = 3;
                this.$nextTick(() => document.getElementById('client_name')?.focus());
            },
            dateLabel(date) {
                const [y, m, d] = date.split('-').map(Number);
                return new Date(Date.UTC(y, m - 1, d, 12)).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' });
            },
            timeLabel(iso) {
                return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZone: this.timezone });
            },
        }));
    });
</script>
@endpush
