{{--
    Share panel for the gallery manager. A draft chooses who can open it and is shared from here; a shared
    gallery offers its link, a WhatsApp message, a QR code and email invitations. Open with
    $dispatch('open-share-panel'). Expects $gallery and $publicUrl.
--}}
@php
    $isDraft = $gallery->isDraft();
    $tz = \App\Models\Gallery::expiryTimezone($gallery->user);
    $client = $gallery->client;
    $firstName = trim(strtok((string) ($client?->name ?: $gallery->client_name), ' ') ?: '');
    $phoneDigits = preg_replace('/\D+/', '', (string) $client?->phone);
    $expiresLabel = $gallery->expires_at?->copy()->setTimezone($tz)->format('F j, Y');
    $shareMessage = collect([
        ($firstName !== '' ? "Hi {$firstName}, your" : 'Your') . " photos from \"{$gallery->title}\" are ready: {$publicUrl}",
        $gallery->visibility === 'password' ? 'Password: ' : null,
        $gallery->download_pin ? "Download PIN: {$gallery->download_pin}" : null,
        $expiresLabel ? "You can download them until {$expiresLabel}." : null,
    ])->filter()->implode("\n");
    $currentExpiresOn = $gallery->expires_at?->copy()->setTimezone($tz)->format('Y-m-d') ?? '';
    $photoCount = $gallery->photo_count;
    $openOnLoad = request()->boolean('share') && ! $isDraft;
@endphp
<div x-data="{
        open: @js($openOnLoad),
        visibility: @js(old('visibility', $gallery->visibility)),
        expiresOn: @js(old('expires_on', $currentExpiresOn)),
        today: @js(now($tz)->format('Y-m-d')),
        message: @js($shareMessage),
        canQr: @js(! $isDraft && $gallery->visibility !== 'private'),
        qr: '',
        pick(months) {
            if (!months) { this.expiresOn = ''; return; }
            const [y, m, d] = this.today.split('-').map(Number);
            const date = new Date(Date.UTC(y, m - 1 + months, d));
            if (date.getUTCDate() !== d) date.setUTCDate(0);
            this.expiresOn = date.toISOString().slice(0, 10);
        },
        async showQr() {
            if (!this.qr) this.qr = await window.qrCodeSvg(@js($publicUrl));
        },
        downloadQr() {
            const svg = new Blob([this.qr], { type: 'image/svg+xml' });
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 1200;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, 1200, 1200);
                ctx.drawImage(img, 0, 0, 1200, 1200);
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = @js(\Illuminate\Support\Str::slug($gallery->title) . '-qr.png');
                a.click();
                URL.revokeObjectURL(img.src);
            };
            img.src = URL.createObjectURL(svg);
        },
        get whatsappUrl() {
            return 'https://wa.me/{{ $phoneDigits }}?text=' + encodeURIComponent(this.message);
        },
     }"
     x-init="$watch('open', v => v && canQr && showQr()); open && canQr && showQr()"
     @open-share-panel.window="open = true"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     x-trap.noscroll="open"
     class="relative z-[80]" role="dialog" aria-modal="true" aria-labelledby="share-panel-title">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/40" @click="open = false"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-border bg-popover text-popover-foreground shadow-xl">
        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
            <h2 id="share-panel-title" class="text-base font-semibold text-foreground">{{ $isDraft ? 'Share gallery' : 'Shared' }}</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm -mr-2" @click="open = false" aria-label="Close"><x-icon name="x" /></button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
            @if($isDraft)
                {{-- Draft: decide who can open it, then share --}}
                <form id="publish-gallery-form" method="POST" action="{{ route('studio.galleries.publish', $gallery->uuid) }}" class="space-y-6">
                    @csrf
                    <p class="text-[13px] text-muted-foreground">
                        This gallery is a draft, so clients can't open the link yet.
                        @if($photoCount === 0) It has no photos yet. @endif
                    </p>

                    <fieldset>
                        <legend class="label">Who can open it</legend>
                        <div class="divide-y divide-border rounded-xl border border-border">
                            @foreach(\App\Models\Gallery::VISIBILITIES as $value => $opt)
                                <label class="flex cursor-pointer items-start gap-3 px-4 py-3 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/40">
                                    <input type="radio" name="visibility" value="{{ $value }}" x-model="visibility" class="mt-1 accent-foreground" @checked(old('visibility', $gallery->visibility) === $value)>
                                    <span>
                                        <span class="block text-sm font-medium text-foreground">{{ $opt['label'] }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ $opt['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('visibility') <p class="error-text">{{ $message }}</p> @enderror
                    </fieldset>

                    <div x-show="visibility === 'password'" @if(old('visibility', $gallery->visibility) !== 'password') style="display: none;" @endif>
                        <x-ui.input :label="$gallery->password_hash ? 'New password (optional)' : 'Password'"
                                    type="password" name="password" id="share-password" autocomplete="new-password"
                                    :placeholder="$gallery->password_hash ? 'Leave blank to keep the current one' : 'At least 6 characters'"
                                    :error="$errors->first('password')" />
                    </div>

                    <div>
                        <span class="label">Expires</span>
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Expiry">
                            <button type="button" class="btn btn-sm" :class="!expiresOn ? 'btn-secondary' : 'btn-outline'" @click="pick(0)">Never</button>
                            <button type="button" class="btn btn-outline btn-sm" @click="pick(1)">1 month</button>
                            <button type="button" class="btn btn-outline btn-sm" @click="pick(3)">3 months</button>
                            <button type="button" class="btn btn-outline btn-sm" @click="pick(6)">6 months</button>
                        </div>
                        <input type="date" name="expires_on" class="input mt-2 sm:w-52" x-model="expiresOn" :min="today" aria-label="Expiry date">
                        @error('expires_on') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </form>
            @else
                @php($vis = \App\Models\Gallery::VISIBILITIES[$gallery->visibility] ?? null)
                <p class="text-[13px] text-muted-foreground">
                    {{ $vis['label'] ?? '' }}: {{ \Illuminate\Support\Str::lower($vis['description'] ?? '') }}
                    @if($expiresLabel) Open until {{ $expiresLabel }}. @endif
                    <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Change</a>
                </p>

                {{-- Link --}}
                <div>
                    <span class="label">Link</span>
                    <div class="flex items-center gap-2 rounded-lg border border-border bg-card p-1 pl-3">
                        <span class="min-w-0 flex-1 truncate font-mono text-[12px] text-muted-foreground" title="{{ $publicUrl }}">{{ preg_replace('#^https?://#', '', $publicUrl) }}</span>
                        <button type="button" class="btn btn-secondary btn-xs shrink-0" @click="$clipboard(@js($publicUrl), 'Gallery link copied')">Copy</button>
                    </div>
                </div>

                @if($gallery->visibility === 'private')
                    <div class="rounded-xl border border-border p-4">
                        <p class="text-sm font-medium text-foreground">Invite people by email</p>
                        <p class="mt-1 text-[13px] text-muted-foreground">A private gallery opens only from a personal invitation, so the link alone won't let anyone in.</p>
                        <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="btn btn-outline btn-sm mt-3">Invite people</a>
                    </div>
                @else
                    {{-- WhatsApp --}}
                    <div>
                        <label for="share-message" class="label">WhatsApp message</label>
                        <textarea id="share-message" rows="4" class="textarea text-[13px]" x-model="message"></textarea>
                        @if($gallery->visibility === 'password')
                            <p class="help">Add the password after "Password:". It isn't stored where we can read it.</p>
                        @endif
                        <a :href="whatsappUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-2">
                            {{ $phoneDigits !== '' ? 'Send to ' . $client->name . ' on WhatsApp' : 'Open WhatsApp' }}
                        </a>
                    </div>

                    {{-- QR code --}}
                    <div>
                        <span class="label">QR code</span>
                        <div class="flex items-start gap-4">
                            <div class="size-32 shrink-0 border border-border bg-white p-1 [&>svg]:size-full" x-html="qr" aria-label="QR code for the gallery link" role="img"></div>
                            <div class="text-[13px] text-muted-foreground">
                                <p>For printed cards and event screens. Scanning it opens the gallery.</p>
                                <button type="button" class="btn btn-outline btn-sm mt-2" @click="downloadQr()" :disabled="!qr">Download PNG</button>
                            </div>
                        </div>
                    </div>

                    {{-- Email --}}
                    <p class="text-[13px] text-muted-foreground">
                        Prefer email? <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="font-medium text-foreground underline underline-offset-2">Invite people from the Clients tab</a> and see when they open it.
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-border px-5 py-3">
            @if($isDraft)
                <button type="button" class="btn btn-ghost" @click="open = false">Not yet</button>
                <button type="submit" form="publish-gallery-form" class="btn btn-primary">Share gallery</button>
            @else
                <form method="POST" action="{{ route('studio.galleries.unpublish', $gallery->uuid) }}"
                      onsubmit="return confirm('Stop sharing? The link stops working until you share the gallery again.')">
                    @csrf
                    <button type="submit" class="btn btn-danger-ghost btn-sm">Stop sharing</button>
                </form>
                <button type="button" class="btn btn-outline btn-sm" @click="open = false">Done</button>
            @endif
        </div>
    </div>
</div>
@if($isDraft && $errors->hasAny(['visibility', 'password', 'expires_on']))
    <div x-data x-init="$nextTick(() => $dispatch('open-share-panel'))"></div>
@endif
