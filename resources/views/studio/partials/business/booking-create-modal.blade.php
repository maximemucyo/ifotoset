{{-- Manual "New booking" modal. Parent provides Alpine `newBookingOpen`. Vars: $clients, $packages, $timezone --}}
@component('studio.partials.business.modal', ['show' => 'newBookingOpen', 'title' => 'New booking', 'description' => 'Add a session booked by phone, WhatsApp or in person. Times are in ' . $timezone . '.', 'icon' => 'calendar-check', 'maxWidth' => 'lg'])
    <form method="POST" action="{{ route('studio.bookings.store') }}"
          x-data="{ clientMode: @js(old('client_name') || $clients->isEmpty() ? 'new' : 'existing'), packageId: @js(old('package_id', '')), status: @js(old('status', 'confirmed')) }">
        @csrf
        <div class="space-y-4 px-5 pt-4 pb-5 sm:px-6">
            <div>
                <div class="flex items-center justify-between">
                    <span class="label mb-0">Client</span>
                    @if($clients->isNotEmpty())
                        <button type="button" class="text-xs font-medium text-muted-foreground hover:text-foreground"
                                @click="clientMode = clientMode === 'new' ? 'existing' : 'new'"
                                x-text="clientMode === 'new' ? 'Choose an existing client' : '+ New client'"></button>
                    @endif
                </div>
                <div x-show="clientMode === 'existing'" class="mt-1.5">
                    <select name="client_id" class="select" :disabled="clientMode !== 'existing'" aria-label="Client">
                        @foreach($clients as $client)
                            <option value="{{ $client->uuid }}" @selected(old('client_id') === (string) $client->uuid)>{{ $client->name }}{{ $client->email ? ' · ' . $client->email : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="clientMode === 'new'" class="mt-1.5 grid gap-3 sm:grid-cols-3">
                    <input type="text" name="client_name" placeholder="Name" maxlength="255" value="{{ old('client_name') }}" class="input" :disabled="clientMode !== 'new'" aria-label="Client name">
                    <input type="email" name="client_email" placeholder="Email (optional)" value="{{ old('client_email') }}" class="input" :disabled="clientMode !== 'new'" aria-label="Client email">
                    <input type="tel" name="client_phone" placeholder="Phone / WhatsApp" value="{{ old('client_phone') }}" class="input" :disabled="clientMode !== 'new'" aria-label="Client phone">
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nb-package" class="label">Package</label>
                    <select id="nb-package" name="package_id" class="select" x-model="packageId">
                        <option value="">Custom session (no package)</option>
                        @foreach($packages as $pkg)
                            <option value="{{ $pkg->uuid }}">{{ $pkg->name }} · {{ $pkg->duration_minutes }} min</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nb-date" class="label">Date</label>
                    <input id="nb-date" type="date" name="date" required value="{{ old('date') }}" class="input">
                </div>
                <div>
                    <label for="nb-time" class="label">Start time</label>
                    <input id="nb-time" type="time" name="time" required step="300" value="{{ old('time') }}" class="input">
                </div>
                <div x-show="!packageId">
                    <label for="nb-duration" class="label">Duration</label>
                    <div class="relative">
                        <input id="nb-duration" type="number" name="duration_minutes" min="15" step="15" value="{{ old('duration_minutes', 60) }}" class="input pr-11 tabular-nums" :disabled="!!packageId">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">min</span>
                    </div>
                </div>
                <div :class="packageId ? 'sm:col-span-2' : ''">
                    <label for="nb-location" class="label">Location <span class="font-normal text-muted-foreground">(optional)</span></label>
                    <input id="nb-location" type="text" name="location" maxlength="255" value="{{ old('location') }}" class="input">
                </div>
            </div>

            <div>
                <label for="nb-notes" class="label">Notes <span class="font-normal text-muted-foreground">(optional)</span></label>
                <textarea id="nb-notes" name="notes" rows="2" maxlength="1000" class="textarea">{{ old('notes') }}</textarea>
            </div>

            <div class="grid gap-2 rounded-lg border border-border bg-muted/40 px-3.5 py-3 text-sm">
                <label class="flex items-center gap-2.5">
                    <input type="radio" name="status" value="confirmed" x-model="status" class="checkbox"> Confirmed
                </label>
                <label class="flex items-center gap-2.5">
                    <input type="radio" name="status" value="pending" x-model="status" class="checkbox"> Pending (not confirmed yet)
                </label>
                <label class="flex items-center gap-2.5" x-show="status === 'confirmed'">
                    <input type="checkbox" name="notify_client" value="1" class="checkbox" @checked(old('notify_client'))> Email the client a confirmation with a calendar invite
                </label>
                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="allow_overlap" value="1" class="checkbox" @checked(old('allow_overlap'))> Book anyway if it overlaps another booking
                </label>
            </div>
        </div>
        @include('studio.partials.business.modal-footer', ['cancel' => 'newBookingOpen = false', 'submit' => 'Add booking', 'icon' => 'plus'])
    </form>
@endcomponent
