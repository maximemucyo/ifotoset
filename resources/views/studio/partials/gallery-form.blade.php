{{--
    Sections of the gallery Settings tab. Expects $gallery. Must be rendered inside a
    <form x-data="{ visibility: ... }"> so the `visibility` state is available.
--}}
@php
    $visibilityValue = old('visibility', $gallery->visibility);
    $visibilityOptions = \App\Models\Gallery::VISIBILITIES;
    $eventDate = old('event_date', $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('Y-m-d') : '');
    $downloadPinEnabled = $gallery->requiresDownloadPin();
    // A PIN is ready the moment the switch goes on
    $downloadPin = $gallery->download_pin ?: str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $expiryTz = \App\Models\Gallery::expiryTimezone(request()->account());
    $todayLocal = now($expiryTz)->format('Y-m-d');
    $timezoneLabel = str_replace('_', ' ', $expiryTz);
    $expiresOn = old('expires_on', $gallery->expires_at ? $gallery->expires_at->copy()->setTimezone($expiryTz)->format('Y-m-d') : '');
    $coverPhoto = $gallery->coverPhoto && ! $gallery->coverPhoto->is_hidden ? $gallery->coverPhoto : null;
    $coverConfig = $coverPhoto ? [
        'uuid' => $coverPhoto->uuid,
        'url' => $coverPhoto->isVideo() ? ($coverPhoto->getPosterUrl('lg') ?? $coverPhoto->getUrl('lg')) : $coverPhoto->getUrl('lg'),
        'width' => $coverPhoto->width ?: 3,
        'height' => $coverPhoto->height ?: 2,
        'x' => (float) old('cover_focal_x', $gallery->cover_focal_x ?? 0.5),
        'y' => (float) old('cover_focal_y', $gallery->cover_focal_y ?? 0.5),
        'photosUrl' => route('studio.galleries.photos', ['uuid' => $gallery->uuid, 'per_page' => 60]),
    ] : null;
    $photoOrder = old('photo_order', $gallery->photo_order);
    $photoOrderHints = [
        'taken'    => 'Follows the camera clock, so the day reads in sequence. Burst shots fall back to file number.',
        'filename' => 'Follows camera file numbers (e.g. IMG_0100, IMG_0103). Useful when camera clocks were out of sync.',
        'uploaded' => 'The order files finished uploading.',
    ];
    if (old('_token')) {
        $downloadPinEnabled = (bool) old('download_pin_enabled');
        $downloadPin = old('download_pin', $downloadPin);
    }
@endphp

<div class="space-y-6">
    {{-- Details --}}
    <section id="details" class="grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Details</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">The title and date appear at the top of the client's gallery. Only you see the client name.</p>
        </div>
        <div class="card space-y-5 p-5 sm:p-6">
            <x-ui.input label="Gallery title"
                        name="title"
                        required
                        placeholder="e.g. Wedding of Diane & Kevin"
                        :value="old('title', $gallery->title)"
                        :error="$errors->first('title')" />

            <div class="grid gap-5 sm:grid-cols-2">
                @include('studio.galleries.partials.client-picker', [
                    'id' => 'gallery-client',
                    'name' => old('client_name', $gallery->client_name),
                    'uuid' => old('client_uuid', $gallery->client?->uuid),
                ])

                <x-ui.input label="Event date"
                            type="date"
                            name="event_date"
                            :value="$eventDate"
                            :error="$errors->first('event_date')" />
            </div>
        </div>
    </section>

    <div class="divider"></div>

    {{-- Cover and focal point --}}
    <section id="cover" class="grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Cover</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">The first thing your client sees. Click the photo where the subject is, so phones and computers both keep it in view.</p>
        </div>
        @if($coverConfig)
            @include('studio.galleries.partials.cover-editor', ['cover' => $coverConfig])
        @else
            <div class="card p-5 text-[13px] text-muted-foreground sm:p-6">
                Upload photos and the first one becomes the cover. You can choose a different one here after that.
            </div>
        @endif
    </section>

    <div class="divider"></div>

    {{-- Privacy & access --}}
    <section id="invitations" class="group/inv grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Privacy and access</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Choose who can open this gallery. You can change this any time.</p>
        </div>
        <div class="card overflow-hidden transition-shadow group-target/inv:ring-2 group-target/inv:ring-brand/30">
            <div class="p-5 sm:p-6">
                <fieldset>
                    <legend class="label">Visibility</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach($visibilityOptions as $value => $opt)
                            <label class="relative flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition-all focus-within:ring-2 focus-within:ring-ring/40"
                                   :class="visibility === '{{ $value }}' ? 'border-foreground bg-muted/40 shadow-xs ring-1 ring-foreground' : 'border-border bg-card hover:border-border-strong'">
                                <div class="flex items-center justify-between">
                                    <span class="flex size-8 items-center justify-center rounded-lg border border-border bg-card transition-colors"
                                          :class="visibility === '{{ $value }}' ? 'text-foreground' : 'text-muted-foreground'">
                                        <x-icon :name="$opt['icon']" class="size-4" />
                                    </span>
                                    <input type="radio" name="visibility" value="{{ $value }}" x-model="visibility" class="sr-only" @checked($visibilityValue === $value)>
                                    <span class="flex size-4 items-center justify-center rounded-full border transition-colors"
                                          :class="visibility === '{{ $value }}' ? 'border-foreground bg-foreground' : 'border-border-strong'">
                                        <span class="size-1.5 rounded-full bg-background" x-show="visibility === '{{ $value }}'"></span>
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-sm font-medium text-foreground">{{ $opt['label'] }}</span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ $opt['description'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('visibility') <p class="error-text">{{ $message }}</p> @enderror
                </fieldset>
            </div>

            <p class="border-t border-border px-5 py-3 text-xs text-muted-foreground sm:px-6">
                <span x-show="visibility === 'private'" @if($visibilityValue !== 'private') style="display: none;" @endif>Only people you invite can open it.</span>
                Invite people and see who opened the gallery on the <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Clients tab</a>.
            </p>

            {{-- Public: Show on profile --}}
            <div x-show="visibility === 'public'" x-cloak class="border-t border-border bg-muted/30 p-5 sm:px-6"
                 style="{{ $visibilityValue === 'public' ? '' : 'display: none;' }}">
                <label class="flex cursor-pointer items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-foreground">Show on my public portfolio</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">When off, the gallery is reachable by direct link only and won't appear on your profile.</span>
                    </span>
                    <span class="switch mt-0.5">
                        <input type="checkbox" name="show_on_profile" value="1" class="sr-only" @checked(old('show_on_profile', $gallery->show_on_profile))>
                        <span></span>
                    </span>
                </label>
            </div>

            {{-- Gallery password --}}
            <div x-show="visibility === 'password'" class="border-t border-border bg-muted/30 p-5 sm:px-6"
                 style="{{ $visibilityValue === 'password' ? '' : 'display: none;' }}">
                <div class="max-w-sm">
                    <x-ui.input :label="$gallery->password_hash ? 'New gallery password' : 'Gallery password'"
                                type="password"
                                name="password"
                                icon="key"
                                autocomplete="new-password"
                                :placeholder="$gallery->password_hash ? 'Leave blank to keep the current password' : 'At least 6 characters'"
                                :hint="$gallery->password_hash ? 'A password is already set. Enter a new one only to change it.' : 'Send this password to your client with the gallery link.'"
                                :error="$errors->first('password')" />
                </div>
            </div>
        </div>
    </section>

    <div class="divider"></div>

    {{-- Expiry --}}
    <section id="expiry" class="grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Expiry</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">After this date the link stops working, including invitations. Extend it any time to reopen the gallery.</p>
        </div>
        <div class="card p-5 sm:p-6"
             x-data="{
                expiresOn: @js($expiresOn),
                today: @js($todayLocal),
                pick(months) {
                    if (!months) { this.expiresOn = ''; return; }
                    const [y, m, d] = this.today.split('-').map(Number);
                    const date = new Date(Date.UTC(y, m - 1 + months, d));
                    // Short months roll over (Jan 31 + 1 month): settle on the last day instead
                    if (date.getUTCDate() !== d) date.setUTCDate(0);
                    this.expiresOn = date.toISOString().slice(0, 10);
                },
             }">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="sm:w-52">
                    <label for="expires_on" class="label">Expires on</label>
                    <input id="expires_on" type="date" name="expires_on" class="input" x-model="expiresOn"
                           value="{{ $expiresOn }}" @unless($gallery->expires_at?->isPast()) min="{{ $todayLocal }}" @endunless>
                </div>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Quick picks">
                    <button type="button" class="btn btn-outline btn-sm" @click="pick(1)">1 month</button>
                    <button type="button" class="btn btn-outline btn-sm" @click="pick(3)">3 months</button>
                    <button type="button" class="btn btn-outline btn-sm" @click="pick(6)">6 months</button>
                    <button type="button" class="btn btn-sm" :class="expiresOn ? 'btn-outline' : 'btn-secondary'" @click="pick(0)">Never</button>
                </div>
            </div>
            <p class="help" x-show="!expiresOn" @if($expiresOn) style="display: none;" @endif>The gallery stays open until you change this.</p>
            <p class="help" x-show="expiresOn" x-cloak>Closes at the end of that day ({{ $timezoneLabel }}).</p>
            @error('expires_on') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="divider"></div>

    {{-- Photo order --}}
    <section id="photo-order" class="grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Photo order</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">How photos are arranged for you and your client.</p>
        </div>
        <div class="card p-5 sm:p-6" x-data="{ order: @js($photoOrder), hints: @js($photoOrderHints) }">
            <label for="photo_order" class="label">Sort photos by</label>
            <select id="photo_order" name="photo_order" class="select max-w-xs" x-model="order">
                @foreach(\App\Models\Gallery::PHOTO_ORDERS as $value => $label)
                    <option value="{{ $value }}" @selected($photoOrder === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="help" x-text="hints[order]">{{ $photoOrderHints[$photoOrder] ?? '' }}</p>
            @error('photo_order') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="divider"></div>

    {{-- Downloads --}}
    <section id="downloads" class="grid scroll-mt-6 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-1">
        <div class="lg:pt-5 xl:pt-0">
            <h2 class="text-[15px] font-semibold text-foreground">Downloads</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Clients can download single photos and the whole gallery.</p>
        </div>
        <div class="card" x-data="{ pinEnabled: @js($downloadPinEnabled), pin: @js((string) $downloadPin) }">
            <label class="flex cursor-pointer items-center justify-between gap-4 p-5 sm:px-6">
                <span>
                    <span class="block text-sm font-medium text-foreground">Download PIN</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground"
                          x-text="pinEnabled ? 'Clients enter this PIN to download.' : 'Off: anyone who opens the gallery can download.'">{{ $downloadPinEnabled ? 'Clients enter this PIN to download.' : 'Off: anyone who opens the gallery can download.' }}</span>
                </span>
                <span class="switch">
                    <input type="hidden" name="download_pin_enabled" value="0">
                    <input type="checkbox" name="download_pin_enabled" value="1" class="sr-only" x-model="pinEnabled" @checked($downloadPinEnabled)>
                    <span></span>
                </span>
            </label>
            <div x-show="pinEnabled" x-cloak class="flex items-center gap-3 border-t border-border px-5 py-4 sm:px-6"
                 style="{{ $downloadPinEnabled ? '' : 'display: none;' }}">
                <input type="hidden" name="download_pin" value="{{ $downloadPin }}" :value="pin" :disabled="!pinEnabled">
                <span class="font-mono text-2xl font-semibold tracking-[0.3em] text-foreground tabular-nums" x-text="pin">{{ $downloadPin }}</span>
                <button type="button" class="btn btn-outline btn-sm" @click="$clipboard(pin, 'PIN copied')"><x-icon name="copy" /> Copy</button>
                <button type="button" class="btn btn-ghost btn-sm"
                        @click="pin = String(crypto.getRandomValues(new Uint32Array(1))[0] % 10000).padStart(4, '0')">
                    <x-icon name="refresh-cw" /> New PIN
                </button>
            </div>
            <p x-show="pinEnabled && pin !== @js((string) $gallery->download_pin) && @js($gallery->requiresDownloadPin())" x-cloak
               class="px-5 pb-4 text-xs text-muted-foreground sm:px-6">
                Save changes to use the new PIN. The old PIN stops working, so send clients the new one.
            </p>
            @error('download_pin')
                <p class="error-text px-5 pb-4 sm:px-6">{{ $message }}</p>
            @enderror
        </div>
    </section>
</div>
