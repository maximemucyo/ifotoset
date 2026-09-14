@extends('layouts.app', ['title' => 'Availability & Scheduling - Studio'])

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'weekly', exceptionModalOpen: false, blockedModalOpen: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Availability &amp; Working Hours</h1>
            <p class="text-xs text-muted-foreground mt-1">Set your recurring weekly working hours, slot durations, and blackout dates for client bookings.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button"
                    x-show="activeTab === 'exceptions'"
                    @click="exceptionModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90 transition-opacity">
                + Add Date Exception
            </button>
            <button type="button"
                    x-show="activeTab === 'blocked'"
                    @click="blockedModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90 transition-opacity">
                + Block Time Period
            </button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex items-center gap-2 border-b border-border pb-4 text-xs font-medium">
        <button type="button"
                @click="activeTab = 'weekly'"
                :class="activeTab === 'weekly' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                class="px-4 py-2 rounded-lg transition-colors">
            Weekly Hours
        </button>
        <button type="button"
                @click="activeTab = 'exceptions'"
                :class="activeTab === 'exceptions' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                class="px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
            <span>Date Exceptions</span>
            @if(count($exceptions) > 0)
                <span class="px-1.5 py-0.2 rounded-full bg-primary/20 text-primary text-[10px] font-bold">{{ count($exceptions) }}</span>
            @endif
        </button>
        <button type="button"
                @click="activeTab = 'blocked'"
                :class="activeTab === 'blocked' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                class="px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
            <span>Blocked Times</span>
            @if(count($blockedSlots) > 0)
                <span class="px-1.5 py-0.2 rounded-full bg-primary/20 text-primary text-[10px] font-bold">{{ count($blockedSlots) }}</span>
            @endif
        </button>
    </div>

    <!-- TAB 1: Weekly Hours -->
    <div x-show="activeTab === 'weekly'" class="space-y-6">
        @php
            $daysOfWeek = [
                0 => 'Sunday',
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
            ];
            $timeOptions = [];
            for ($h = 0; $h < 24; $h++) {
                foreach (['00', '30'] as $m) {
                    $val = sprintf('%02d:%s', $h, $m);
                    $timeOptions[] = $val;
                }
            }
        @endphp

        <form method="POST" action="{{ route('studio.availability.settings') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Days List -->
                <div class="lg:col-span-2 space-y-3">
                    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-border">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Day of Week</span>
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Working Hours</span>
                        </div>

                        @foreach($daysOfWeek as $dayIndex => $dayName)
                            @php
                                $daySetting = $settings[$dayIndex] ?? ['day_of_week' => $dayIndex, 'start_time' => '09:00', 'end_time' => '17:00', 'is_active' => true];
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between py-2.5 border-b border-border/50 gap-3"
                                 x-data="{ active: {{ $daySetting['is_active'] ? 'true' : 'false' }} }">
                                <input type="hidden" name="settings[{{ $dayIndex }}][day_of_week]" value="{{ $dayIndex }}">

                                <label class="flex items-center gap-3 cursor-pointer select-none sm:w-40">
                                    <input type="checkbox"
                                           name="settings[{{ $dayIndex }}][is_active]"
                                           value="1"
                                           x-model="active"
                                           class="rounded border-border text-primary focus:ring-primary w-4 h-4">
                                    <span class="font-semibold text-sm" :class="active ? 'text-foreground' : 'text-muted-foreground line-through'">
                                        {{ $dayName }}
                                    </span>
                                </label>

                                <div class="flex items-center gap-2" x-show="active">
                                    <select name="settings[{{ $dayIndex }}][start_time]"
                                            class="rounded-lg border border-border bg-input px-2.5 py-1.5 text-xs text-foreground focus:border-primary focus:outline-none">
                                        @foreach($timeOptions as $t)
                                            <option value="{{ $t }}" {{ $daySetting['start_time'] === $t ? 'selected' : '' }}>
                                                {{ date('g:i A', strtotime($t)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-xs text-muted-foreground">to</span>
                                    <select name="settings[{{ $dayIndex }}][end_time]"
                                            class="rounded-lg border border-border bg-input px-2.5 py-1.5 text-xs text-foreground focus:border-primary focus:outline-none">
                                        @foreach($timeOptions as $t)
                                            <option value="{{ $t }}" {{ $daySetting['end_time'] === $t ? 'selected' : '' }}>
                                                {{ date('g:i A', strtotime($t)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="text-xs text-muted-foreground italic" x-show="!active">
                                    Unavailable / Closed
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Timezone & Interval Preferences -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-5">
                        <h3 class="font-bold text-sm text-foreground">Scheduling Rules</h3>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-medium text-muted-foreground">Photographer Timezone</label>
                            <select name="timezone" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                                @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" {{ old('timezone', $user->timezone ?? 'Africa/Kigali') === $tz ? 'selected' : '' }}>
                                        {{ $tz }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-medium text-muted-foreground">Slot Duration (Interval)</label>
                            <select name="slot_interval_minutes" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                                <option value="15" {{ old('slot_interval_minutes', $user->slot_interval_minutes ?? 30) == 15 ? 'selected' : '' }}>15 Minutes</option>
                                <option value="30" {{ old('slot_interval_minutes', $user->slot_interval_minutes ?? 30) == 30 ? 'selected' : '' }}>30 Minutes (Recommended)</option>
                                <option value="60" {{ old('slot_interval_minutes', $user->slot_interval_minutes ?? 30) == 60 ? 'selected' : '' }}>60 Minutes (1 Hour)</option>
                            </select>
                            <p class="text-[11px] text-muted-foreground mt-1 leading-relaxed">
                                Client booking slots will be generated based on this interval.
                            </p>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-primary text-primary-foreground font-semibold text-xs shadow-sm hover:opacity-90 transition-opacity">
                                Save Weekly Hours
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 2: Date Exceptions -->
    <div x-show="activeTab === 'exceptions'" class="space-y-4" style="display: none;">
        <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Status / Working Hours</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($exceptions as $ex)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4 font-semibold text-foreground">
                                {{ $ex->date->format('l, F j, Y') }}
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($ex->is_closed)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-destructive/10 text-destructive">
                                        Day Off (Closed)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-600">
                                        Special Hours: {{ date('g:i A', strtotime($ex->start_time)) }} &ndash; {{ date('g:i A', strtotime($ex->end_time)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('studio.availability.exceptions.destroy', $ex->uuid) }}" onsubmit="return confirm('Remove this date exception?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-muted-foreground hover:text-destructive transition-colors">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-muted-foreground">
                                No custom date exceptions added yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: Blocked / Blackout Time Slots -->
    <div x-show="activeTab === 'blocked'" class="space-y-4" style="display: none;">
        <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">From</th>
                        <th class="px-6 py-4">To</th>
                        <th class="px-6 py-4">Reason</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($blockedSlots as $slot)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4 text-xs font-medium text-foreground">
                                {{ $slot->starts_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-foreground">
                                {{ $slot->ends_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $slot->reason ?? 'Personal Blackout' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('studio.availability.blocked.destroy', $slot->uuid) }}" onsubmit="return confirm('Unblock this time slot?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-muted-foreground hover:text-destructive transition-colors">
                                        Unblock
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                No blackout time slots blocked.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Exception Modal -->
    <div x-show="exceptionModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="exceptionModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-2xl bg-card border border-border p-6 shadow-2xl text-left"
                 x-data="{ isClosed: false }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Add Specific Date Exception</h3>
                    <button type="button" @click="exceptionModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('studio.availability.exceptions.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Date</label>
                        <input type="date" name="date" required min="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-1">
                        <input type="checkbox" name="is_closed" value="1" x-model="isClosed" class="rounded border-border text-primary focus:ring-primary w-4 h-4">
                        <span class="text-xs font-medium text-foreground">Mark entire day as Closed (Day Off)</span>
                    </label>

                    <div x-show="!isClosed" class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Start Time</label>
                            <input type="time" name="start_time" value="09:00" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">End Time</label>
                            <input type="time" name="end_time" value="17:00" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="exceptionModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Save Exception</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Blocked Slot Modal -->
    <div x-show="blockedModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="blockedModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-2xl bg-card border border-border p-6 shadow-2xl text-left">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Block Out Time Range</h3>
                    <button type="button" @click="blockedModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('studio.availability.blocked.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Starts At</label>
                        <input type="datetime-local" name="starts_at" required class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Ends At</label>
                        <input type="datetime-local" name="ends_at" required class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Reason (Optional)</label>
                        <input type="text" name="reason" placeholder="e.g. Vacation, Gear maintenance" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="blockedModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Block Time</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
