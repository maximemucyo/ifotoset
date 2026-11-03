{{--
    Shared sections for the gallery create / edit forms.
    Expects: $gallery (Gallery|null), $invitations (Collection|null). Must be rendered inside a
    <form x-data="{ visibility: ... }"> so the `visibility` state is available.
--}}
@php
    $isEdit = isset($gallery) && $gallery;
    $visibilityValue = old('visibility', $isEdit ? $gallery->visibility : 'public');
    $visibilityOptions = [
        'public'   => ['label' => 'Public',        'icon' => 'globe', 'description' => 'Anyone with the direct link.'],
        'password' => ['label' => 'Password',      'icon' => 'key',   'description' => 'Anyone with the link and the password.'],
        'private'  => ['label' => 'Private',       'icon' => 'lock',  'description' => 'Only invited client emails can open it.'],
    ];
    $eventDate = old('event_date', $isEdit
        ? ($gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('Y-m-d') : '')
        : date('Y-m-d'));
    $photoDownloads = $isEdit ? (bool) $gallery->allow_photo_downloads : true;
    $galleryDownloads = $isEdit ? (bool) $gallery->allow_gallery_downloads : true;
    $downloadPinEnabled = $isEdit && $gallery->requiresDownloadPin();
    // Suggest a fresh PIN so turning the switch on is all it takes
    $downloadPin = $isEdit && $gallery->download_pin ? $gallery->download_pin : str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $expiryTz = \App\Models\Gallery::expiryTimezone(request()->account());
    $todayLocal = now($expiryTz)->format('Y-m-d');
    $timezoneLabel = str_replace('_', ' ', $expiryTz);
    $expiresOn = old('expires_on', $isEdit && $gallery->expires_at ? $gallery->expires_at->copy()->setTimezone($expiryTz)->format('Y-m-d') : '');
    $photoOrder = old('photo_order', $isEdit ? $gallery->photo_order : 'taken');
    $photoOrderHints = [
        'taken'    => 'Follows the camera clock, so the day reads in sequence. Burst shots fall back to file number.',
        'filename' => 'Follows camera file numbers (e.g. IMG_0100, IMG_0103). Useful when camera clocks were out of sync.',
        'uploaded' => 'The order files finished uploading.',
    ];
    if (old('_token')) {
        $photoDownloads = (bool) old('allow_photo_downloads');
        $galleryDownloads = (bool) old('allow_gallery_downloads');
        $downloadPinEnabled = (bool) old('download_pin_enabled');
        $downloadPin = old('download_pin', $downloadPin);
    }
@endphp

<div class="space-y-6">
    {{-- Details --}}
    <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="lg:pt-5">
            <h2 class="text-[15px] font-semibold text-foreground">Details</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">The title and date appear at the top of the client's gallery. Only you see the client name.</p>
        </div>
        <div class="card space-y-5 p-5 sm:p-6">
            <x-ui.input label="Gallery title"
                        name="title"
                        required
                        :autofocus="! $isEdit"
                        placeholder="e.g. Wedding of Diane & Kevin"
                        :value="old('title', $isEdit ? $gallery->title : '')"
                        :error="$errors->first('title')" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Client name (optional)"
                            name="client_name"
                            icon="user"
                            placeholder="Diane Mukamisha"
                            :value="old('client_name', $isEdit ? $gallery->client_name : '')"
                            :error="$errors->first('client_name')" />

                <x-ui.input label="Event date"
                            type="date"
                            name="event_date"
                            :value="$eventDate"
                            :error="$errors->first('event_date')" />
            </div>
        </div>
    </section>

    <div class="divider"></div>

    {{-- Privacy & access (anchor: #invitations, linked from the gallery manager) --}}
    <section id="invitations" class="group/inv grid scroll-mt-20 gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="lg:pt-5">
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

            @if($isEdit)
                <p class="border-t border-border px-5 py-3 text-xs text-muted-foreground sm:px-6">
                    <span x-show="visibility === 'private'" @if($visibilityValue !== 'private') style="display: none;" @endif>Only people you invite can open it.</span>
                    Invite people and see who opened the gallery on the <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Clients tab</a>.
                </p>
            @endif

            {{-- Public: Show on profile --}}
            <div x-show="visibility === 'public'" x-cloak class="border-t border-border bg-muted/30 p-5 sm:px-6"
                 style="{{ $visibilityValue === 'public' ? '' : 'display: none;' }}">
                <label class="flex cursor-pointer items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-foreground">Show on my public portfolio</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">When off, the gallery is reachable by direct link only and won't appear on your profile.</span>
                    </span>
                    <span class="switch mt-0.5">
                        <input type="checkbox" name="show_on_profile" value="1" class="sr-only" @checked(old('show_on_profile', $isEdit ? $gallery->show_on_profile : false))>
                        <span></span>
                    </span>
                </label>
            </div>

            {{-- Gallery password --}}
            <div x-show="visibility === 'password'" class="border-t border-border bg-muted/30 p-5 sm:px-6"
                 style="{{ $visibilityValue === 'password' ? '' : 'display: none;' }}">
                <div class="max-w-sm">
                    <x-ui.input :label="$isEdit && $gallery->password_hash ? 'New gallery password' : 'Gallery password'"
                                type="password"
                                name="password"
                                icon="key"
                                autocomplete="new-password"
                                :placeholder="$isEdit && $gallery->password_hash ? 'Leave blank to keep the current password' : 'At least 6 characters'"
                                :hint="$isEdit && $gallery->password_hash ? 'A password is already set. Enter a new one only to change it.' : 'Send this password to your client with the gallery link.'"
                                :error="$errors->first('password')" />
                </div>
            </div>

            {{-- Private: email invitations at creation; later they live on the Clients tab --}}
            @unless($isEdit)
                <div x-show="visibility === 'private'" class="space-y-4 border-t border-border bg-muted/30 p-5 sm:px-6"
                     style="{{ $visibilityValue === 'private' ? '' : 'display: none;' }}">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-foreground">Invite people</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">Each person gets a personal link by email. You can also invite people later.</p>
                        </div>
                        <a href="{{ route('studio.galleries.invitations.template') }}" class="btn btn-ghost btn-xs self-start text-muted-foreground">
                            <x-icon name="download" /> CSV template
                        </a>
                    </div>

                    @include('studio.galleries.partials.invite-fields')
                </div>
            @endunless
        </div>
    </section>

    <div class="divider"></div>

    {{-- Expiry --}}
    <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="lg:pt-5">
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
                           value="{{ $expiresOn }}" @if(! $isEdit || ! $gallery->expires_at?->isPast()) min="{{ $todayLocal }}" @endif>
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
    <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="lg:pt-5">
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
    <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="lg:pt-5">
            <h2 class="text-[15px] font-semibold text-foreground">Downloads</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Control what your client can take home.</p>
        </div>
        <div class="card divide-y divide-border">
            <label class="flex cursor-pointer items-start justify-between gap-4 p-5 sm:px-6">
                <span class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="image" class="size-4" /></span>
                    <span>
                        <span class="block text-sm font-medium text-foreground">Individual photo downloads</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">Clients can download single photos in full resolution.</span>
                    </span>
                </span>
                <span class="switch mt-1.5">
                    <input type="hidden" name="allow_photo_downloads" value="0">
                    <input type="checkbox" name="allow_photo_downloads" value="1" class="sr-only" @checked($photoDownloads)>
                    <span></span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start justify-between gap-4 p-5 sm:px-6">
                <span class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="download" class="size-4" /></span>
                    <span>
                        <span class="block text-sm font-medium text-foreground">Full gallery download</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">Clients can download the entire collection as a ZIP file.</span>
                    </span>
                </span>
                <span class="switch mt-1.5">
                    <input type="hidden" name="allow_gallery_downloads" value="0">
                    <input type="checkbox" name="allow_gallery_downloads" value="1" class="sr-only" @checked($galleryDownloads)>
                    <span></span>
                </span>
            </label>
            <div x-data="{ pinEnabled: @js($downloadPinEnabled) }">
                <label class="flex cursor-pointer items-start justify-between gap-4 p-5 sm:px-6">
                    <span class="flex items-start gap-3">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="key" class="size-4" /></span>
                        <span>
                            <span class="block text-sm font-medium text-foreground">Require a download PIN</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">Anyone can view the gallery, but downloading asks for this PIN. Share it only with the people who should take photos home.</span>
                        </span>
                    </span>
                    <span class="switch mt-1.5">
                        <input type="hidden" name="download_pin_enabled" value="0">
                        <input type="checkbox" name="download_pin_enabled" value="1" class="sr-only" x-model="pinEnabled" @checked($downloadPinEnabled)>
                        <span></span>
                    </span>
                </label>
                <div x-show="pinEnabled" x-cloak class="-mt-1 px-5 pb-5 sm:px-6 sm:pl-[4.25rem]"
                     style="{{ $downloadPinEnabled ? '' : 'display: none;' }}">
                    <div class="max-w-[12rem]">
                        <x-ui.input label="Download PIN"
                                    name="download_pin"
                                    inputmode="numeric"
                                    maxlength="4"
                                    pattern="[0-9]{4}"
                                    autocomplete="off"
                                    class="font-mono tracking-[0.3em]"
                                    :value="$downloadPin"
                                    hint="4 digits."
                                    :error="$errors->first('download_pin')" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
