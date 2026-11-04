{{--
    Sections of the gallery Settings tab: Details, Privacy, Expiry, Downloads, Watermark and Favorites. Expects $gallery, and the
    gallery workspace's `section` in scope. Wide screens show the section picked in the rail; phones show them all.
    Each form saves on its own through `settingsForm`: choices and switches as they change, text with a Save button.
--}}
@php
    $updateUrl = route('studio.galleries.update', $gallery->uuid);
    $visibilityOptions = \App\Models\Gallery::VISIBILITIES;
    $eventDate = $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('Y-m-d') : '';
    // A PIN is ready the moment the switch goes on
    $downloadPin = $gallery->download_pin ?: str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $expiryTz = \App\Models\Gallery::expiryTimezone(request()->account());
    $todayLocal = now($expiryTz)->format('Y-m-d');
    $timezoneLabel = str_replace('_', ' ', $expiryTz);
    $expiresOn = $gallery->expires_at ? $gallery->expires_at->copy()->setTimezone($expiryTz)->format('Y-m-d') : '';
    $photoOrderHints = [
        'taken'    => 'Follows the camera clock, so the day reads in sequence. Burst shots fall back to file number.',
        'filename' => 'Follows camera file numbers (e.g. IMG_0100, IMG_0103). Useful when camera clocks were out of sync.',
        'uploaded' => 'The order files finished uploading.',
    ];
    $sectionClass = 'scroll-mt-6';
@endphp

<div class="max-w-3xl space-y-12 lg:space-y-0">
    {{-- ============ Details ============ --}}
    <section id="details" class="{{ $sectionClass }}" :class="section === 'details' ? '' : 'lg:hidden'" aria-labelledby="details-title">
        <header class="mb-5">
            <h2 id="details-title" class="text-lg font-semibold text-foreground">Details</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">The title and date appear at the top of the client's gallery. Only you see the client name.</p>
        </header>

        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" @submit.prevent="save('Details saved')" class="card space-y-5 p-5 sm:p-6" novalidate>
            @csrf
            @method('PATCH')
            <div>
                <x-ui.input label="Gallery title" name="title" required placeholder="e.g. Wedding of Diane & Kevin"
                            :value="$gallery->title" ::class="error('title') && 'input-error'" />
                <p class="error-text" x-show="error('title')" x-text="error('title')" x-cloak></p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    @include('studio.galleries.partials.client-picker', [
                        'id' => 'gallery-client',
                        'name' => $gallery->client_name,
                        'uuid' => $gallery->client?->uuid,
                    ])
                    <p class="error-text" x-show="error('client_name')" x-text="error('client_name')" x-cloak></p>
                </div>
                <div>
                    <x-ui.input label="Event date" type="date" name="event_date" :value="$eventDate" ::class="error('event_date') && 'input-error'" />
                    <p class="error-text" x-show="error('event_date')" x-text="error('event_date')" x-cloak></p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <x-icon name="loader-circle" class="animate-spin" x-show="saving" x-cloak />
                    Save details
                </button>
            </div>
        </form>

        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" class="card mt-4 p-5 sm:p-6"
              @submit.prevent="save('Photo order saved')">
            @csrf
            @method('PATCH')
            <div x-data="{ order: @js($gallery->photo_order), hints: @js($photoOrderHints) }">
                <label for="photo_order" class="label">Sort photos by</label>
                <select id="photo_order" name="photo_order" class="select max-w-xs" x-model="order" @change="$nextTick(() => save('Photo order saved'))">
                    @foreach(\App\Models\Gallery::PHOTO_ORDERS as $value => $label)
                        <option value="{{ $value }}" @selected($gallery->photo_order === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="help" x-text="hints[order]">{{ $photoOrderHints[$gallery->photo_order] ?? '' }}</p>
            </div>
        </form>
    </section>

    {{-- ============ Privacy ============ --}}
    <section id="privacy" class="{{ $sectionClass }}" :class="section === 'privacy' ? '' : 'lg:hidden'" aria-labelledby="privacy-title">
        <header class="mb-5">
            <h2 id="privacy-title" class="text-lg font-semibold text-foreground">Privacy</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Choose who can open this gallery. You can change it any time.</p>
        </header>

        <div x-data="{
                visibility: @js($gallery->visibility),
                hasPassword: @js((bool) $gallery->password_hash),
                // A password gallery needs a password before it can be saved
                choose() {
                    if (this.visibility === 'password' && !this.hasPassword) {
                        this.$nextTick(() => document.getElementById('gallery-password').focus());
                        return;
                    }
                    this.save('Saved');
                },
             }">
        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" class="card overflow-hidden" novalidate
              @submit.prevent="save('Password saved').then(ok => { if (ok) { hasPassword = true; $refs.password.value = '' } })">
            @csrf
            @method('PATCH')
                <fieldset>
                    <legend class="sr-only">Who can open it</legend>
                    @foreach($visibilityOptions as $value => $opt)
                        <label @class(['flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/40 sm:px-6', 'border-t border-border' => ! $loop->first])>
                            <x-icon :name="$opt['icon']" class="size-[18px] shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-foreground">{{ $opt['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">{{ $opt['description'] }}</span>
                            </span>
                            <input type="radio" name="visibility" value="{{ $value }}" x-model="visibility" @change="$nextTick(() => choose())"
                                   class="size-4 shrink-0" @checked($gallery->visibility === $value)>
                        </label>

                        @if($value === 'public')
                            <div x-show="visibility === 'public'" class="border-t border-dashed border-border bg-muted/30 px-5 py-4 sm:px-6"
                                 @if($gallery->visibility !== 'public') style="display: none;" @endif>
                                <label class="flex cursor-pointer items-start justify-between gap-4 sm:pl-[34px]">
                                    <span>
                                        <span class="block text-sm font-medium text-foreground">Show on my portfolio</span>
                                        <span class="mt-0.5 block text-xs text-muted-foreground">When off, only people with the link can find it.</span>
                                    </span>
                                    <span class="switch mt-0.5">
                                        <input type="checkbox" name="show_on_profile" value="1" class="sr-only" @change="$nextTick(() => save())"
                                               @checked($gallery->show_on_profile) :disabled="visibility !== 'public'">
                                        <span></span>
                                    </span>
                                </label>
                            </div>
                        @elseif($value === 'password')
                            <div x-show="visibility === 'password'" class="border-t border-dashed border-border bg-muted/30 px-5 py-4 sm:px-6"
                                 @if($gallery->visibility !== 'password') style="display: none;" @endif>
                                <div class="grid gap-4 sm:grid-cols-2 sm:pl-[34px]">
                                    <div>
                                        <label for="gallery-password" class="label" x-text="hasPassword ? 'New password' : 'Password'">{{ $gallery->password_hash ? 'New password' : 'Password' }}</label>
                                        <input id="gallery-password" type="password" name="password" x-ref="password" class="input" autocomplete="new-password"
                                               :class="error('password') && 'input-error'"
                                               :placeholder="hasPassword ? 'Leave blank to keep it' : 'At least 6 characters'"
                                               placeholder="{{ $gallery->password_hash ? 'Leave blank to keep it' : 'At least 6 characters' }}">
                                        <p class="error-text" x-show="error('password')" x-text="error('password')" x-cloak></p>
                                    </div>
                                    <div>
                                        <label for="gallery-password-hint" class="label">Hint <span class="font-normal text-muted-foreground">(optional)</span></label>
                                        <input id="gallery-password-hint" type="text" name="password_hint" class="input" maxlength="100"
                                               value="{{ $gallery->password_hint }}" placeholder="e.g. The wedding date">
                                        <p class="help">Shown above the password box.</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center justify-between gap-3 sm:pl-[34px]">
                                    <p class="text-xs text-muted-foreground" x-text="hasPassword ? 'Send the password to your client with the link.' : 'Choose a password, then save.'"></p>
                                    <button type="submit" class="btn btn-primary btn-sm shrink-0" :disabled="saving">
                                        <x-icon name="loader-circle" class="animate-spin" x-show="saving" x-cloak />
                                        Save
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </fieldset>

            <p class="border-t border-border px-5 py-3 text-xs text-muted-foreground sm:px-6">
                Invite people by email and see who opened the gallery on the
                <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Clients tab</a>.
            </p>
        </form>
        </div>
    </section>

    {{-- ============ Expiry ============ --}}
    <section id="expiry" class="{{ $sectionClass }}" :class="section === 'expiry' ? '' : 'lg:hidden'" aria-labelledby="expiry-title">
        <header class="mb-5">
            <h2 id="expiry-title" class="text-lg font-semibold text-foreground">Expiry</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">After this date the link stops working, invitations too. Choose a later date any time to reopen the gallery.</p>
        </header>

        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" class="card p-5 sm:p-6" @submit.prevent="save('Expiry saved')">
            @csrf
            @method('PATCH')
            <div x-data="{
                    expiresOn: @js($expiresOn),
                    today: @js($todayLocal),
                    pick(months) {
                        if (!months) {
                            this.expiresOn = '';
                        } else {
                            const [y, m, d] = this.today.split('-').map(Number);
                            const date = new Date(Date.UTC(y, m - 1 + months, d));
                            // Short months roll over (Jan 31 + 1 month): settle on the last day instead
                            if (date.getUTCDate() !== d) date.setUTCDate(0);
                            this.expiresOn = date.toISOString().slice(0, 10);
                        }
                        this.$nextTick(() => this.save('Expiry saved'));
                    },
                 }">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="sm:w-52">
                        <label for="expires_on" class="label">Expires on</label>
                        <input id="expires_on" type="date" name="expires_on" class="input" x-model="expiresOn"
                               :class="error('expires_on') && 'input-error'"
                               @change="$nextTick(() => save('Expiry saved'))"
                               value="{{ $expiresOn }}" @unless($gallery->expires_at?->isPast()) min="{{ $todayLocal }}" @endunless>
                    </div>
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Quick picks">
                        <button type="button" class="btn btn-outline btn-sm" @click="pick(1)">1 month</button>
                        <button type="button" class="btn btn-outline btn-sm" @click="pick(3)">3 months</button>
                        <button type="button" class="btn btn-outline btn-sm" @click="pick(6)">6 months</button>
                        <button type="button" class="btn btn-sm" :class="expiresOn ? 'btn-outline' : 'btn-secondary'" @click="pick(0)">Never</button>
                    </div>
                </div>
                <p class="error-text" x-show="error('expires_on')" x-text="error('expires_on')" x-cloak></p>
                <p class="help" x-show="!error('expires_on') && !expiresOn" @if($expiresOn) style="display: none;" @endif>The gallery stays open until you change this.</p>
                <p class="help" x-show="!error('expires_on') && expiresOn" @unless($expiresOn) style="display: none;" @endunless>Closes at the end of that day ({{ $timezoneLabel }}).</p>
            </div>
        </form>
    </section>

    {{-- ============ Downloads ============ --}}
    <section id="downloads" class="{{ $sectionClass }}" :class="section === 'downloads' ? '' : 'lg:hidden'" aria-labelledby="downloads-title">
        <header class="mb-5">
            <h2 id="downloads-title" class="text-lg font-semibold text-foreground">Downloads</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Clients can download single photos and the whole gallery.</p>
        </header>

        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" class="card" @submit.prevent="save()">
            @csrf
            @method('PATCH')
            <div x-data="{
                    pinEnabled: @js($gallery->requiresDownloadPin()),
                    pin: @js((string) $downloadPin),
                    savedPin: @js((string) $gallery->download_pin),
                    toggle() {
                        this.$nextTick(() => this.save(this.pinEnabled ? 'Download PIN on' : 'Download PIN off')
                            .then(ok => ok && (this.savedPin = this.pinEnabled ? this.pin : '')));
                    },
                    renew() {
                        if (this.savedPin && !confirm(`Make a new PIN? PIN ${this.savedPin} stops working, so send clients the new one.`)) return;
                        this.pin = String(crypto.getRandomValues(new Uint32Array(1))[0] % 10000).padStart(4, '0');
                        this.toggle();
                    },
                 }">
                <label class="flex cursor-pointer items-center justify-between gap-4 p-5 sm:px-6">
                    <span>
                        <span class="block text-sm font-medium text-foreground">Download PIN</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground"
                              x-text="pinEnabled ? 'Clients enter this PIN to download.' : 'Off: anyone who opens the gallery can download.'">{{ $gallery->requiresDownloadPin() ? 'Clients enter this PIN to download.' : 'Off: anyone who opens the gallery can download.' }}</span>
                    </span>
                    <span class="switch">
                        <input type="hidden" name="download_pin_enabled" value="0">
                        <input type="checkbox" name="download_pin_enabled" value="1" class="sr-only" x-model="pinEnabled" @change="toggle()" @checked($gallery->requiresDownloadPin())>
                        <span></span>
                    </span>
                </label>
                <div x-show="pinEnabled" class="flex flex-wrap items-center gap-3 border-t border-border px-5 py-4 sm:px-6"
                     @unless($gallery->requiresDownloadPin()) style="display: none;" @endunless>
                    <input type="hidden" name="download_pin" value="{{ $downloadPin }}" :value="pin" :disabled="!pinEnabled">
                    <span class="mr-2 text-2xl font-semibold tracking-[0.3em] text-foreground tabular-nums" x-text="pin">{{ $downloadPin }}</span>
                    <button type="button" class="btn btn-outline btn-sm" @click="$clipboard(pin, 'PIN copied')"><x-icon name="copy" /> Copy</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="renew()" :disabled="saving"><x-icon name="refresh-cw" /> New PIN</button>
                </div>
                <p class="error-text px-5 pb-4 sm:px-6" x-show="error('download_pin')" x-text="error('download_pin')" x-cloak></p>
            </div>
        </form>
    </section>
    {{-- ============ Watermark ============ --}}
    @php
        $watermark = \App\Support\Gallery\Watermark::for($gallery->user);
        $watermarkAllowed = \App\Support\Gallery\Watermark::allowedFor($gallery->user);
        $watermarkProgress = $gallery->watermark && $watermarkAllowed ? $watermark->progress($gallery) : ['done' => 0, 'total' => 0];
    @endphp
    <section id="watermark" class="{{ $sectionClass }}" :class="section === 'watermark' ? '' : 'lg:hidden'" aria-labelledby="watermark-title">
        <header class="mb-5">
            <h2 id="watermark-title" class="text-lg font-semibold text-foreground">Watermark</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Your mark on the photos clients see. Downloads are the clean photos.</p>
        </header>

        <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" class="card" @submit.prevent="save()">
            @csrf
            @method('PATCH')
            <div x-data="{
                    enabled: @js((bool) $gallery->watermark),
                    done: {{ $watermarkProgress['done'] }},
                    total: {{ $watermarkProgress['total'] }},
                    timer: null,
                    init() { if (this.enabled && this.done < this.total) this.poll() },
                    toggle() {
                        this.$nextTick(async () => {
                            if (await this.save(this.enabled ? 'Watermark on' : 'Watermark off') && this.enabled) this.poll();
                        });
                    },
                    // The previews are made in the background; follow them until every photo has one
                    poll() {
                        clearTimeout(this.timer);
                        this.timer = setTimeout(async () => {
                            try {
                                const res = await fetch(@js(route('studio.galleries.watermark', $gallery->uuid)), { headers: { Accept: 'application/json' } });
                                Object.assign(this, await res.json());
                            } catch {}
                            if (this.enabled && this.done < this.total) this.poll();
                        }, 3000);
                    },
                 }">
                <label @class(['flex items-center justify-between gap-4 p-5 sm:px-6', 'cursor-pointer' => $watermarkAllowed || $gallery->watermark])>
                    <span>
                        <span class="flex items-center gap-2 text-sm font-medium text-foreground">Watermark
                            @unless($watermarkAllowed)<x-ui.badge variant="neutral"><x-icon name="lock" class="size-3" /> Paid plans</x-ui.badge>@endunless
                        </span>
                        <span class="mt-0.5 block text-xs text-muted-foreground"
                              x-text="enabled ? @js('On: ' . $watermark->summary() . '.') : 'Off: clients see the photos without it.'">{{ $gallery->watermark ? 'On: ' . $watermark->summary() . '.' : 'Off: clients see the photos without it.' }}</span>
                    </span>
                    <span class="switch">
                        <input type="hidden" name="watermark" value="0">
                        <input type="checkbox" name="watermark" value="1" class="sr-only" x-model="enabled" @change="toggle()" @checked($gallery->watermark)
                               @unless($watermarkAllowed) :disabled="!enabled" @disabled(! $gallery->watermark) @endunless>
                        <span></span>
                    </span>
                </label>
                <div x-show="enabled && done < total" x-cloak class="border-t border-border px-5 py-4 sm:px-6" aria-live="polite">
                    <p class="text-[13px] text-foreground">Adding it to your photos: <span class="tabular-nums" x-text="`${done} of ${total}`"></span></p>
                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-foreground transition-[width] duration-500" :style="`width: ${total ? Math.round(done / total * 100) : 0}%`"></div>
                    </div>
                    <p class="help mt-2">Until a photo has it, clients see that photo without it.</p>
                </div>
                <p x-show="enabled && summary.downloads === 'No PIN'" x-cloak class="border-t border-border px-5 py-4 text-[13px] text-muted-foreground sm:px-6">
                    Clients can still download the clean photos. Turn on the <a href="#downloads" class="font-medium text-foreground underline underline-offset-2">Download PIN</a> to stop that.
                </p>
                @unless($watermarkAllowed)
                    <p class="border-t border-border px-5 py-4 text-[13px] text-muted-foreground sm:px-6">
                        {{ $gallery->watermark ? "Your plan doesn't include watermarks any more. Photos that have one keep it, but new photos won't get one." : 'Watermarks come with every paid plan.' }}
                        @can('studio.billing')
                            <a href="{{ route('studio.billing.index') }}" class="font-medium text-foreground underline underline-offset-2">See plans</a>
                        @endcan
                    </p>
                @endunless
                @can('studio.profile.manage')
                    <p class="border-t border-border px-5 py-4 text-[13px] sm:px-6">
                        <a href="{{ route('studio.settings.index') }}#watermark" class="font-medium text-foreground underline underline-offset-2">Change how it looks</a>
                        <span class="text-muted-foreground">in Settings. It's the same on every gallery.</span>
                    </p>
                @endcan
            </div>
        </form>
    </section>

    {{-- ============ Favorites ============ --}}
    <section id="favorites" class="{{ $sectionClass }}" :class="section === 'favorites' ? '' : 'lg:hidden'" aria-labelledby="favorites-title">
        <header class="mb-5">
            <h2 id="favorites-title" class="text-lg font-semibold text-foreground">Favorites</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Clients tap the heart on the photos they like and send you their picks. You see them on the
                <a href="{{ route('studio.galleries.activity', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Activity tab</a>.</p>
        </header>

        <div class="card" x-data="{ enabled: @js((bool) $gallery->favorites_enabled) }">
            <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" @submit.prevent="save()">
                @csrf
                @method('PATCH')
                <label class="flex cursor-pointer items-center justify-between gap-4 p-5 sm:px-6">
                    <span>
                        <span class="block text-sm font-medium text-foreground">Favorites</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground"
                              x-text="enabled ? 'On: clients can pick photos.' : 'Off: clients can\'t pick photos.'">{{ $gallery->favorites_enabled ? 'On: clients can pick photos.' : "Off: clients can't pick photos." }}</span>
                    </span>
                    <span class="switch">
                        <input type="hidden" name="favorites_enabled" value="0">
                        <input type="checkbox" name="favorites_enabled" value="1" class="sr-only" x-model="enabled"
                               @change="$nextTick(() => save(enabled ? 'Favorites on' : 'Favorites off'))" @checked($gallery->favorites_enabled)>
                        <span></span>
                    </span>
                </label>
            </form>

            <form method="POST" action="{{ $updateUrl }}" x-data="settingsForm" x-show="enabled" novalidate
                  class="border-t border-border px-5 py-4 sm:px-6" @submit.prevent="save('Limit saved')"
                  @unless($gallery->favorites_enabled) style="display: none;" @endunless>
                @csrf
                @method('PATCH')
                <label for="favorites_limit" class="label">Most photos each client can pick</label>
                <div class="flex items-center gap-2">
                    <input id="favorites_limit" type="number" name="favorites_limit" min="1" max="5000" inputmode="numeric"
                           class="input w-28" placeholder="No limit" value="{{ $gallery->favorites_limit }}"
                           :class="error('favorites_limit') && 'input-error'">
                    <button type="submit" class="btn btn-outline" :disabled="saving">
                        <x-icon name="loader-circle" class="animate-spin" x-show="saving" x-cloak />
                        Save
                    </button>
                </div>
                <p class="error-text" x-show="error('favorites_limit')" x-text="error('favorites_limit')" x-cloak></p>
                <p class="help" x-show="!error('favorites_limit')">For example 30 for an album. Leave it empty for no limit.</p>
            </form>
        </div>
    </section>
</div>
