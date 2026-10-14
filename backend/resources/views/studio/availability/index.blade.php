@extends('layouts.app', ['title' => 'Availability · ifotoset'])

@section('content')
@php
    $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
    $timeOptions = [];
    for ($h = 0; $h < 24; $h++) {
        foreach (['00', '30'] as $m) {
            $timeOptions[] = sprintf('%02d:%s', $h, $m);
        }
    }
    // Saved-schedule summary
    $openDays = collect($settings)->where('is_active', true);
    $weeklyMinutes = $openDays->sum(function ($s) {
        $start = strtotime($s['start_time']); $end = strtotime($s['end_time']);
        return $end > $start ? ($end - $start) / 60 : 0;
    });
    $weeklyHours = rtrim(rtrim(number_format($weeklyMinutes / 60, 1), '0'), '.');
    $currentInterval = (int) old('slot_interval_minutes', $user->slot_interval_minutes ?? 30);
    $currentTz = old('timezone', $user->timezone ?? 'Africa/Kigali');
    $upcomingExceptions = $exceptions->filter(fn ($e) => $e->date->endOfDay()->isFuture())->count();
    $activeBlocked = $blockedSlots->filter(fn ($b) => $b->ends_at->isFuture())->count();
@endphp

<div x-data="{ activeTab: 'weekly', exceptionModalOpen: false, blockedModalOpen: false }">
    <x-ui.page-header title="Availability" description="Your weekly working hours, one-off date changes and blocked time. Clients can only book inside these windows.">
        <x-slot:actions>
            <button type="button" @click="exceptionModalOpen = true" class="btn" :class="activeTab === 'exceptions' ? 'btn-primary' : 'btn-outline'">
                <x-icon name="calendar-x" /> Add date exception
            </button>
            <button type="button" @click="blockedModalOpen = true" class="btn" :class="activeTab === 'blocked' ? 'btn-primary' : 'btn-outline'">
                <x-icon name="ban" /> Block time
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($errors->any())
        <div class="alert alert-danger mb-6">
            <x-icon name="circle-alert" />
            <div>
                <p class="font-medium">We couldn't save that.</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-[13px]">
                    @foreach(collect($errors->all())->unique()->take(4) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="tabs" role="tablist" aria-label="Availability sections">
            <button type="button" role="tab" class="tab whitespace-nowrap" :aria-selected="activeTab === 'weekly'" @click="activeTab = 'weekly'">
                <x-icon name="clock" /> Weekly hours
            </button>
            <button type="button" role="tab" class="tab whitespace-nowrap" :aria-selected="activeTab === 'exceptions'" @click="activeTab = 'exceptions'">
                <x-icon name="calendar-x" /> Date exceptions
                @if(count($exceptions) > 0)
                    <span class="rounded bg-muted px-1 text-[11px] tabular-nums text-muted-foreground">{{ count($exceptions) }}</span>
                @endif
            </button>
            <button type="button" role="tab" class="tab whitespace-nowrap" :aria-selected="activeTab === 'blocked'" @click="activeTab = 'blocked'">
                <x-icon name="ban" /> Blocked time
                @if(count($blockedSlots) > 0)
                    <span class="rounded bg-muted px-1 text-[11px] tabular-nums text-muted-foreground">{{ count($blockedSlots) }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- TAB 1: Weekly hours --}}
    <div x-show="activeTab === 'weekly'">
        <form method="POST" action="{{ route('studio.availability.settings') }}">
            @csrf

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Day editor --}}
                <section class="card overflow-hidden lg:col-span-2">
                    <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                        <div>
                            <h2 class="text-[15px] font-semibold text-foreground">Weekly schedule</h2>
                            <p class="mt-0.5 text-[13px] text-muted-foreground">Toggle the days you work and set your hours.</p>
                        </div>
                        <span class="hidden items-center gap-1.5 text-xs text-muted-foreground sm:inline-flex">
                            <x-icon name="globe" class="size-3.5" /> {{ $currentTz }}
                        </span>
                    </header>

                    <ul class="divide-y divide-border">
                        @foreach($daysOfWeek as $dayIndex => $dayName)
                            @php
                                $daySetting = $settings[$dayIndex] ?? ['day_of_week' => $dayIndex, 'start_time' => '09:00', 'end_time' => '17:00', 'is_active' => true];
                            @endphp
                            <li class="group flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5 transition-colors sm:flex-nowrap sm:px-5"
                                :class="active ? '' : 'bg-muted/30'"
                                x-data="{
                                    active: {{ $daySetting['is_active'] ? 'true' : 'false' }},
                                    start: @js($daySetting['start_time']),
                                    end: @js($daySetting['end_time']),
                                    get minutes() {
                                        const [sh, sm] = this.start.split(':').map(Number), [eh, em] = this.end.split(':').map(Number);
                                        return (eh * 60 + em) - (sh * 60 + sm);
                                    },
                                    get label() {
                                        const m = this.minutes; if (m <= 0) return '';
                                        const h = Math.floor(m / 60), r = m % 60;
                                        return h + 'h' + (r ? ' ' + r + 'm' : '');
                                    }
                                }"
                                x-on:apply-hours.window="if (active) { start = $event.detail.start; end = $event.detail.end }">
                                <input type="hidden" name="settings[{{ $dayIndex }}][day_of_week]" value="{{ $dayIndex }}">

                                <div class="flex flex-1 items-center gap-3 sm:w-40 sm:flex-none">
                                    <label class="switch" title="Toggle {{ $dayName }}">
                                        <input type="checkbox" class="sr-only"
                                               name="settings[{{ $dayIndex }}][is_active]"
                                               value="1"
                                               x-model="active"
                                               aria-label="Available on {{ $dayName }}">
                                        <span></span>
                                    </label>
                                    <span class="text-sm font-medium transition-colors" :class="active ? 'text-foreground' : 'text-muted-foreground'">{{ $dayName }}</span>
                                </div>

                                <div class="order-last flex w-full min-w-0 flex-wrap items-center gap-2 sm:order-none sm:w-auto sm:flex-1">
                                    <div class="flex items-center gap-2" x-show="active">
                                        <select name="settings[{{ $dayIndex }}][start_time]" x-model="start" class="select input-sm w-[7.5rem] tabular-nums" aria-label="{{ $dayName }} start time">
                                            @foreach($timeOptions as $t)
                                                <option value="{{ $t }}" {{ $daySetting['start_time'] === $t ? 'selected' : '' }}>{{ date('g:i A', strtotime($t)) }}</option>
                                            @endforeach
                                        </select>
                                        <x-icon name="arrow-right" class="size-3.5 text-subtle" />
                                        <select name="settings[{{ $dayIndex }}][end_time]" x-model="end" class="select input-sm w-[7.5rem] tabular-nums" aria-label="{{ $dayName }} end time">
                                            @foreach($timeOptions as $t)
                                                <option value="{{ $t }}" {{ $daySetting['end_time'] === $t ? 'selected' : '' }}>{{ date('g:i A', strtotime($t)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{-- Keep the time fields submitted (validation needs them) while visually showing "Unavailable" --}}
                                    <span x-show="!active" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-dashed border-border px-2.5 text-[13px] text-muted-foreground">
                                        <x-icon name="moon" class="size-3.5" /> Unavailable
                                    </span>
                                    <span x-show="active && minutes <= 0" class="inline-flex items-center gap-1 text-xs font-medium text-destructive">
                                        <x-icon name="circle-alert" class="size-3.5" /> End must be after start
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5" x-show="active">
                                    <span class="rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-muted-foreground" x-show="label" x-text="label"></span>
                                    <button type="button"
                                            class="btn btn-ghost btn-xs text-muted-foreground opacity-100 max-lg:w-7 max-lg:px-0 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                                            @click="$dispatch('apply-hours', { start, end }); window.toast && window.toast('Applied {{ $dayName }}\'s hours to all open days', 'success')"
                                            title="Copy these hours to every open day">
                                        <x-icon name="copy" /> <span class="sr-only lg:not-sr-only">Copy to all</span>
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <footer class="flex flex-col-reverse gap-3 border-t border-border bg-muted/40 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted-foreground">Changes apply to new bookings only. Existing sessions are kept.</p>
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="check" /> Save schedule
                        </button>
                    </footer>
                </section>

                {{-- Rules + summary --}}
                <aside class="space-y-6">
                    <section class="card p-5">
                        <h2 class="text-[15px] font-semibold text-foreground">Booking rules</h2>
                        <p class="mt-0.5 text-[13px] text-muted-foreground">How your free time turns into bookable slots.</p>

                        <div class="mt-5 space-y-5">
                            <div>
                                <label for="timezone" class="label">Timezone</label>
                                <div class="input-group">
                                    <x-icon name="globe" class="input-icon" />
                                    <select id="timezone" name="timezone" class="select pl-9">
                                        @foreach($timezones as $tz)
                                            <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ str_replace('_', ' ', $tz) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <fieldset>
                                <legend class="label">Slot interval</legend>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach([15 => '15 min', 30 => '30 min', 60 => '1 hour'] as $mins => $lbl)
                                        <label class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border border-border bg-card px-2 py-2.5 text-center transition hover:border-border-strong has-[:checked]:border-foreground has-[:checked]:bg-muted/60 has-[:checked]:ring-1 has-[:checked]:ring-foreground has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring">
                                            <input type="radio" name="slot_interval_minutes" value="{{ $mins }}" class="sr-only" {{ $currentInterval === $mins ? 'checked' : '' }}>
                                            <span class="text-sm font-medium text-foreground">{{ $lbl }}</span>
                                            @if($mins === 30)
                                                <span class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Default</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                                <p class="help">Start times offered to clients are spaced by this interval.</p>
                            </fieldset>
                        </div>
                    </section>

                    <section class="card p-5">
                        <p class="eyebrow">Saved schedule</p>
                        <div class="mt-3 grid grid-cols-2 gap-4">
                            <div>
                                <div class="stat-value">{{ $openDays->count() }}<span class="text-base font-medium text-muted-foreground">/7</span></div>
                                <p class="mt-1 text-xs text-muted-foreground">Working days</p>
                            </div>
                            <div>
                                <div class="stat-value">{{ $weeklyHours }}<span class="text-base font-medium text-muted-foreground">h</span></div>
                                <p class="mt-1 text-xs text-muted-foreground">Hours per week</p>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-1" aria-hidden="true">
                            @foreach($daysOfWeek as $dayIndex => $dayName)
                                @php $on = (bool) ($settings[$dayIndex]['is_active'] ?? false); @endphp
                                <div class="flex flex-1 flex-col items-center gap-1">
                                    <span class="h-1.5 w-full rounded-full {{ $on ? 'bg-brand' : 'bg-muted' }}"></span>
                                    <span class="text-[10px] font-medium {{ $on ? 'text-foreground' : 'text-subtle' }}">{{ mb_substr($dayName, 0, 1) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 space-y-2 border-t border-border pt-4 text-[13px]">
                            <button type="button" @click="activeTab = 'exceptions'" class="flex w-full items-center justify-between text-muted-foreground transition hover:text-foreground">
                                <span class="inline-flex items-center gap-2"><x-icon name="calendar-x" class="size-3.5" /> Upcoming exceptions</span>
                                <span class="font-medium tabular-nums text-foreground">{{ $upcomingExceptions }}</span>
                            </button>
                            <button type="button" @click="activeTab = 'blocked'" class="flex w-full items-center justify-between text-muted-foreground transition hover:text-foreground">
                                <span class="inline-flex items-center gap-2"><x-icon name="ban" class="size-3.5" /> Active blocks</span>
                                <span class="font-medium tabular-nums text-foreground">{{ $activeBlocked }}</span>
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>

    {{-- TAB 2: Date exceptions --}}
    <div x-show="activeTab === 'exceptions'" style="display: none;">
        @if($exceptions->isEmpty())
            <x-ui.empty-state icon="calendar-x"
                title="No date exceptions"
                description="Taking a holiday or working different hours on a specific day? Add an exception and it overrides your weekly schedule for that date."
                action-label="Add date exception" action-click="exceptionModalOpen = true" />
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($exceptions as $ex)
                    @php $isPast = $ex->date->copy()->endOfDay()->isPast(); @endphp
                    <li class="card flex items-center gap-4 p-4 {{ $isPast ? 'opacity-60' : '' }}">
                        <div class="flex w-12 shrink-0 flex-col items-center overflow-hidden rounded-lg border border-border bg-card text-center leading-none">
                            <span class="w-full py-1 text-[10px] font-semibold uppercase tracking-wider {{ $ex->is_closed ? 'bg-destructive/10 text-destructive' : 'bg-info/10 text-info' }}">{{ $ex->date->format('M') }}</span>
                            <span class="py-1.5 font-display text-lg font-semibold tabular-nums text-foreground">{{ $ex->date->format('j') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ $ex->date->format('l, M j, Y') }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                @if($ex->is_closed)
                                    <x-ui.badge variant="danger" dot>Closed all day</x-ui.badge>
                                @else
                                    <x-ui.badge variant="info"><x-icon name="clock" /> {{ date('g:i A', strtotime($ex->start_time)) }} – {{ date('g:i A', strtotime($ex->end_time)) }}</x-ui.badge>
                                @endif
                                @if($isPast)
                                    <span class="text-[11px] text-subtle">Past</span>
                                @elseif($ex->date->isToday())
                                    <span class="text-[11px] font-medium text-brand">Today</span>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('studio.availability.exceptions.destroy', $ex->uuid) }}"
                              @submit.prevent="$dispatch('confirm-action', { form: $el, title: 'Remove this exception?', message: @js($ex->date->format('l, M j') . ' will go back to your regular weekly hours.'), confirm: 'Remove' })">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-icon btn-sm text-muted-foreground hover:text-destructive" title="Remove exception" aria-label="Remove exception for {{ $ex->date->format('M j, Y') }}">
                                <x-icon name="trash-2" />
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- TAB 3: Blocked time --}}
    <div x-show="activeTab === 'blocked'" style="display: none;">
        @if($blockedSlots->isEmpty())
            <x-ui.empty-state icon="ban"
                title="No blocked time"
                description="Block out a range for travel, gear maintenance or personal time. Clients won't be able to book anything that overlaps it."
                action-label="Block time" action-click="blockedModalOpen = true" />
        @else
            <div class="card overflow-hidden">
                <ul class="divide-y divide-border">
                    @foreach($blockedSlots as $blockedSlot)
                        @php
                            $isPast = $blockedSlot->ends_at->isPast();
                            $isNow = !$isPast && $blockedSlot->starts_at->isPast();
                            $sameDay = $blockedSlot->starts_at->isSameDay($blockedSlot->ends_at);
                        @endphp
                        <li class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center {{ $isPast ? 'opacity-60' : '' }}">
                            <span class="hidden size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground sm:flex">
                                <x-icon name="ban" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-foreground">{{ $blockedSlot->reason ?: 'Blocked time' }}</p>
                                    @if($isNow)
                                        <x-ui.badge variant="warning" dot>In effect</x-ui.badge>
                                    @elseif($isPast)
                                        <x-ui.badge variant="neutral">Ended</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[13px] tabular-nums text-muted-foreground">
                                    <span>{{ $blockedSlot->starts_at->format('D, M j · g:i A') }}</span>
                                    <x-icon name="arrow-right" class="size-3 text-subtle" />
                                    <span>{{ $sameDay ? $blockedSlot->ends_at->format('g:i A') : $blockedSlot->ends_at->format('D, M j · g:i A') }}</span>
                                    <span class="text-subtle">· {{ $blockedSlot->starts_at->diffForHumans($blockedSlot->ends_at, true) }}</span>
                                </p>
                            </div>
                            <form method="POST" action="{{ route('studio.availability.blocked.destroy', $blockedSlot->uuid) }}"
                                  @submit.prevent="$dispatch('confirm-action', { form: $el, title: 'Unblock this time?', message: 'Clients will be able to book this period again (within your working hours).', confirm: 'Unblock', tone: 'default', icon: 'circle-help' })">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">
                                    <x-icon name="lock-open" /> Unblock
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Add exception modal --}}
    @component('studio.partials.business.modal', ['show' => 'exceptionModalOpen', 'title' => 'Add date exception', 'description' => 'Override your weekly hours for a single date.', 'icon' => 'calendar-x', 'maxWidth' => 'md'])
        <form method="POST" action="{{ route('studio.availability.exceptions.store') }}" x-data="{ isClosed: false }">
            @csrf
            <div class="space-y-4 px-5 pt-4 pb-5 sm:px-6">
                <div>
                    <label for="ex-date" class="label">Date</label>
                    <input id="ex-date" type="date" name="date" required autofocus min="{{ date('Y-m-d') }}" class="input">
                </div>

                <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-border bg-muted/40 px-3.5 py-3">
                    <span>
                        <span class="block text-sm font-medium text-foreground">Closed all day</span>
                        <span class="block text-xs text-muted-foreground">No bookings will be offered on this date.</span>
                    </span>
                    <span class="switch">
                        <input type="checkbox" name="is_closed" value="1" x-model="isClosed" class="sr-only">
                        <span></span>
                    </span>
                </label>

                <div x-show="!isClosed" x-transition.opacity class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="ex-start" class="label">Start time</label>
                        <input id="ex-start" type="time" name="start_time" value="09:00" class="input tabular-nums">
                    </div>
                    <div>
                        <label for="ex-end" class="label">End time</label>
                        <input id="ex-end" type="time" name="end_time" value="17:00" class="input tabular-nums">
                    </div>
                </div>
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'exceptionModalOpen = false', 'submit' => 'Save exception'])
        </form>
    @endcomponent

    {{-- Block time modal --}}
    @component('studio.partials.business.modal', ['show' => 'blockedModalOpen', 'title' => 'Block time', 'description' => 'Prevent bookings during a specific period.', 'icon' => 'ban', 'maxWidth' => 'md'])
        <form method="POST" action="{{ route('studio.availability.blocked.store') }}">
            @csrf
            <div class="space-y-4 px-5 pt-4 pb-5 sm:px-6">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="bl-start" class="label">Starts</label>
                        <input id="bl-start" type="datetime-local" name="starts_at" required autofocus class="input">
                    </div>
                    <div>
                        <label for="bl-end" class="label">Ends</label>
                        <input id="bl-end" type="datetime-local" name="ends_at" required class="input">
                    </div>
                </div>
                <div>
                    <label for="bl-reason" class="label">Reason <span class="font-normal text-muted-foreground">(optional)</span></label>
                    <input id="bl-reason" type="text" name="reason" maxlength="255" placeholder="e.g. Vacation, gear maintenance" class="input">
                    <p class="help">Only visible to you.</p>
                </div>
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'blockedModalOpen = false', 'submit' => 'Block time'])
        </form>
    @endcomponent

    @include('studio.partials.business.confirm-dialog')
</div>
@endsection
