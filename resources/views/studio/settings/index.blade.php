@extends('layouts.app', ['title' => 'Settings · ifotoset'])

@php
    $hasPasswordErrors = $errors->hasAny(['current_password', 'password', 'delete_password', 'confirm']);
    $initialTab = $hasPasswordErrors ? 'security' : ($errors->has('username') ? 'public' : 'profile');
    $publicUrl = $user->username ? $user->public_url : null;
    $rootHost = config('app.public_root_host', 'ifotoset.com');

    // In a team studio, $user is the studio (owner) and $me is the person signed in.
    // Members edit only their own name, photo and password there.
    $teamContext = isset($studioAccount) && $studioAccount->isTeamContext();
    $me = $teamContext ? $actor : $user;

    $sections = array_values(array_filter([
        ['id' => 'profile', 'label' => 'Profile', 'icon' => 'user', 'hint' => 'Name, photo and contact'],
        Gate::allows('studio.profile.manage') ? ['id' => 'public', 'label' => 'Public profile', 'icon' => 'globe', 'hint' => 'Portfolio link and visibility'] : null,
        ['id' => 'security', 'label' => 'Security', 'icon' => 'shield', 'hint' => 'Password'],
        $teamContext ? null : ['id' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'hint' => 'Email alerts'],
    ]));
@endphp

@section('breadcrumbs')
    <span>Account</span>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Settings</span>
@endsection

@section('content')
<div x-data="{
        tab: '{{ $initialTab }}',
        sections: @js(array_column($sections, 'id')),
        init() {
            const h = window.location.hash.replace('#', '');
            if (this.sections.includes(h)) this.tab = h;
        },
        go(id) {
            this.tab = id;
            history.replaceState(null, '', '#' + id);
        }
     }">
    <x-ui.page-header title="Settings" description="Manage your photographer profile, sign-in security and the emails we send you." />

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10">
        {{-- ============ Section nav ============ --}}
        <nav aria-label="Settings sections" class="min-w-0 lg:sticky lg:top-6 lg:self-start">
            {{-- Mobile: horizontal scroll pills --}}
            <div class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:hidden" role="tablist">
                @foreach($sections as $s)
                    <button type="button" role="tab" @click="go('{{ $s['id'] }}')"
                            :aria-selected="(tab === '{{ $s['id'] }}').toString()"
                            :class="tab === '{{ $s['id'] }}' ? 'bg-card text-foreground shadow-xs ring-1 ring-border' : 'text-muted-foreground hover:text-foreground'"
                            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium transition">
                        <x-icon :name="$s['icon']" class="size-3.5" /> {{ $s['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Desktop: vertical list --}}
            <div class="hidden space-y-0.5 lg:block" role="tablist" aria-orientation="vertical">
                @foreach($sections as $s)
                    <button type="button" role="tab" @click="go('{{ $s['id'] }}')"
                            :aria-selected="(tab === '{{ $s['id'] }}').toString()"
                            :class="tab === '{{ $s['id'] }}' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'"
                            class="group flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium transition">
                        <x-icon :name="$s['icon']" class="size-4" />
                        <span class="flex-1">{{ $s['label'] }}</span>
                        <span x-show="tab === '{{ $s['id'] }}'" class="size-1.5 rounded-full bg-brand"></span>
                    </button>
                @endforeach
                @can('studio.billing')
                    <div class="my-3 h-px bg-border"></div>
                    <a href="{{ route('studio.billing.index') }}" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-muted-foreground transition hover:bg-muted/60 hover:text-foreground">
                        <x-icon name="credit-card" class="size-4" />
                        <span class="flex-1">Billing &amp; plans</span>
                        <x-icon name="arrow-up-right" class="size-3.5 opacity-60" />
                    </a>
                @endcan
            </div>
        </nav>

        <div class="min-w-0 space-y-6">

            {{-- ============ Profile ============ --}}
            @if($teamContext)
                <section x-show="tab === 'profile'" class="card" aria-labelledby="team-heading">
                    <div class="card-header">
                        <div>
                            <h2 id="team-heading" class="card-title">Team studio</h2>
                            <p class="card-description">You're working in <span class="font-medium text-foreground">{{ $user->name }}</span> as {{ strtolower($studioAccount->role()->label()) }}. Its profile, plan and billing are managed by the owner.</p>
                        </div>
                        <span class="flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="building" class="size-4" /></span>
                    </div>
                    <form method="POST" action="{{ route('studio.team.leave') }}" class="card-footer flex justify-end"
                          onsubmit="return confirm(@js('Leave ' . $user->name . '? You will lose access to its galleries, bookings and clients.'))">
                        @csrf
                        <button type="submit" class="btn btn-danger-ghost"><x-icon name="log-out" /> Leave team</button>
                    </form>
                </section>
            @endif
            <section x-show="tab === 'profile'" class="card" aria-labelledby="profile-heading">
                <div class="card-header">
                    <div>
                        <h2 id="profile-heading" class="card-title">{{ $teamContext ? 'Your profile' : 'Profile' }}</h2>
                        <p class="card-description">{{ $teamContext ? 'Your own name and photo, shown to the rest of the team.' : 'This is how clients see you on galleries, booking pages and emails.' }}</p>
                    </div>
                </div>

                {{-- Avatar uploader (uploads directly; independent of the form below) --}}
                <div class="border-b border-border p-5" x-data="avatarUploader({
                        current: @js($me->avatar_url),
                        requestUrl: '{{ url('/api/v1/settings/avatar/request') }}',
                        confirmUrl: '{{ url('/api/v1/settings/avatar/confirm') }}'
                     })">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="relative size-20 shrink-0">
                            <template x-if="preview">
                                <img :src="preview" alt="Profile photo" width="80" height="80" decoding="async" class="size-20 rounded-full object-cover ring-1 ring-border">
                            </template>
                            <template x-if="!preview">
                                <x-ui.avatar :name="$me->name" size="xl" class="!size-20 text-xl" />
                            </template>
                            <div x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 text-white">
                                <x-icon name="loader-circle" class="size-5 animate-spin" />
                            </div>
                            <button type="button" @click="$refs.file.click()" :disabled="uploading"
                                    class="absolute -bottom-0.5 -right-0.5 flex size-7 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-sm transition hover:bg-muted"
                                    aria-label="Change profile photo">
                                <x-icon name="camera" class="size-3.5" />
                            </button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground">Profile photo</p>
                            <p class="mt-0.5 text-[13px] text-muted-foreground">Square images work best. JPG, PNG or WebP up to 10 MB. We resize it to 400 × 400.</p>
                            <p x-show="error" x-cloak class="error-text" x-text="error"></p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" @click="$refs.file.click()" :disabled="uploading" class="btn btn-outline btn-sm">
                                    <x-icon name="upload" x-show="!uploading" />
                                    <x-icon name="loader-circle" class="animate-spin" x-show="uploading" x-cloak />
                                    <span x-text="uploading ? statusText : (preview ? 'Replace photo' : 'Upload photo')">Upload photo</span>
                                </button>
                                <span x-show="uploading" x-cloak class="w-24"><span class="progress block"><span :style="`width: ${progress}%`"></span></span></span>
                            </div>
                        </div>
                    </div>
                    <input type="file" x-ref="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)" tabindex="-1" aria-hidden="true">
                </div>

                <form method="POST" action="{{ route('studio.settings.profile') }}">
                    @csrf
                    <div class="card-body space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input label="Display name" name="name" required autocomplete="name"
                                        :value="old('name', $me->name)" :error="$errors->first('name')" />
                            @unless($teamContext)
                            <x-ui.phone-input label="Phone / WhatsApp" placeholder="788 123 456"
                                              :value="$user->phone" :error="$errors->first('phone')" />
                            @endunless
                        </div>

                        @unless($teamContext)

                        <div x-data="{ count: {{ mb_strlen((string) old('bio', $user->bio)) }} }">
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="bio" class="label !mb-0">Bio</label>
                                <span class="text-xs tabular-nums text-muted-foreground"><span x-text="count"></span> / 1000</span>
                            </div>
                            <textarea name="bio" id="bio" rows="4" maxlength="1000" @input="count = $event.target.value.length"
                                      class="textarea {{ $errors->has('bio') ? 'input-error' : '' }}"
                                      placeholder="Tell clients about your style, experience and the sessions you love shooting.">{{ old('bio', $user->bio) }}</textarea>
                            @error('bio') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            @php
                                $cityName = $user->cityName();
                                $locationHint = match (true) {
                                    $cityName !== null => "Matched to {$cityName}, so clients looking for a photographer there can find you.",
                                    filled($user->location) => "We couldn't match this to a city yet. It still shows on your portfolio.",
                                    default => 'Your town or neighbourhood, for example Kimihurura, Kigali.',
                                };
                            @endphp
                            <x-ui.input label="Location" name="location" placeholder="Kimihurura, Kigali" icon="map-pin" list="location-suggestions" autocomplete="off"
                                        :hint="$locationHint"
                                        :value="old('location', $user->location)" :error="$errors->first('location')" />
                            <datalist id="location-suggestions">
                                @foreach(\App\Support\Directory::locationSuggestions() as $suggestion)
                                    <option value="{{ $suggestion }}"></option>
                                @endforeach
                            </datalist>
                            <x-ui.input label="Other website" type="url" name="website" icon="link"
                                        :placeholder="$publicUrl ?? 'https://yourstudio.com'"
                                        :hint="$publicUrl ? 'Your ifotoset portfolio is already your website. Only add an address here if you have a separate site.' : null"
                                        :value="old('website', $user->website)" :error="$errors->first('website')" />
                        </div>

                        @php $chosenSpecialties = old('specialties', $user->specialties ?? []); @endphp
                        <fieldset x-data="{ chosen: @js(array_values($chosenSpecialties)), max: {{ config('directory.max_specialties') }} }">
                            <div class="mb-1.5 flex items-center justify-between">
                                <legend class="label !mb-0">Specialties</legend>
                                <span class="text-xs tabular-nums text-muted-foreground"><span x-text="chosen.length"></span> / {{ config('directory.max_specialties') }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach(config('directory.specialties') as $slug => $label)
                                    <label class="cursor-pointer" :class="!chosen.includes(@js($slug)) && chosen.length >= max && 'cursor-not-allowed opacity-50'">
                                        <input type="checkbox" name="specialties[]" value="{{ $slug }}" class="peer sr-only" x-model="chosen"
                                               :disabled="!chosen.includes(@js($slug)) && chosen.length >= max">
                                        <span class="inline-flex h-8 items-center gap-1.5 rounded-full border border-border bg-card px-3 text-[13px] text-muted-foreground transition-colors hover:border-border-strong hover:text-foreground peer-checked:border-brand/50 peer-checked:bg-brand/10 peer-checked:text-foreground peer-checked:[&>svg]:inline peer-focus-visible:ring-4 peer-focus-visible:ring-ring/15">
                                            <x-icon name="check" class="hidden size-3.5 text-brand" />
                                            {{ $label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('specialties') <p class="error-text">{{ $message }}</p> @else <p class="help">The kinds of sessions you shoot most. Choose up to {{ config('directory.max_specialties') }}.</p> @enderror
                            @error('specialties.*') <p class="error-text">{{ $message }}</p> @enderror
                        </fieldset>
                        @endunless

                        <div>
                            <span class="label">Email</span>
                            <div class="flex h-10 items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 px-3 text-sm">
                                <span class="truncate text-muted-foreground">{{ $me->email }}</span>
                                @if($me->hasVerifiedEmail())
                                    <x-ui.badge variant="success"><x-icon name="check" class="size-3" /> Verified</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning">Unverified</x-ui.badge>
                                @endif
                            </div>
                            <p class="help">Your sign-in email. Contact support to change it.</p>
                        </div>
                    </div>
                    <div class="card-footer flex items-center justify-end gap-3">
                        @unless($teamContext)
                            <p class="mr-auto hidden text-xs text-muted-foreground sm:block">Shown on your public portfolio.</p>
                        @endunless
                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </div>
                </form>
            </section>

            {{-- ============ Public profile ============ --}}
            @can('studio.profile.manage')
            <section x-show="tab === 'public'" x-cloak class="card" aria-labelledby="public-heading">
                <form method="POST" action="{{ route('studio.settings.public-profile') }}"
                      x-data="{ username: @js(old('username', $user->username)), original: @js($user->username), isPublic: @js((bool) old('profile_public', $user->profile_public ?? false)) }">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="public-heading" class="card-title">Public profile</h2>
                            <p class="card-description">Your portfolio and booking page, shared with clients.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-6">
                        <div>
                            <label for="username" class="label">Portfolio address</label>
                            <div class="flex h-10 items-stretch overflow-hidden rounded-lg border bg-input text-sm shadow-xs transition focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15 {{ $errors->has('username') ? 'border-destructive' : 'border-border' }}">
                                <input type="text" id="username" name="username" required autocomplete="off" spellcheck="false"
                                       x-model="username" @input="username = $event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50)"
                                       class="min-w-0 flex-1 bg-transparent px-3 text-foreground outline-none placeholder:text-subtle">
                                <span class="flex items-center border-l border-border bg-muted px-3 text-[13px] text-muted-foreground">.{{ $rootHost }}</span>
                            </div>
                            @error('username')
                                <p class="error-text">{{ $message }}</p>
                            @else
                                <p class="help" x-show="username === original">This is the link clients use to find your portfolio and galleries.</p>
                                <p class="help !text-warning" x-show="username !== original" x-cloak>
                                    Changing your address updates every portfolio and gallery link. Links you've already shared will stop working.
                                </p>
                            @enderror
                        </div>

                        @if($publicUrl)
                            <div class="rounded-xl border border-border p-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="globe" class="size-4" /></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground">Portfolio link</p>
                                            <p class="truncate text-[13px] text-muted-foreground">{{ $publicUrl }}</p>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <button type="button" class="btn btn-outline btn-sm" @click="$clipboard(@js($publicUrl))"><x-icon name="copy" /> Copy</button>
                                        <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="external-link" /> Open</a>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-border p-4">
                            <span class="flex min-w-0 items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                      :class="isPublic ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'">
                                    <x-icon name="eye" class="size-4" x-show="isPublic" />
                                    <x-icon name="eye-off" class="size-4" x-show="!isPublic" x-cloak />
                                </span>
                                <span>
                                    <span class="block text-sm font-medium text-foreground">Public portfolio</span>
                                    <span class="mt-0.5 block text-[13px] text-muted-foreground" x-show="isPublic">Anyone with your link can view your portfolio and request a booking. It can appear in search results.</span>
                                    <span class="mt-0.5 block text-[13px] text-muted-foreground" x-show="!isPublic" x-cloak>Your portfolio page is hidden and removed from search. Shared gallery links keep working.</span>
                                </span>
                            </span>
                            <span class="switch mt-1">
                                <input type="checkbox" name="profile_public" value="1" class="sr-only" x-model="isPublic">
                                <span></span>
                            </span>
                        </label>
                    </div>
                    <div class="card-footer flex items-center justify-between gap-3">
                        <p class="text-xs text-muted-foreground">Change its template, logo and sections in the <a href="{{ route('studio.portfolio.edit') }}" class="link">portfolio builder</a>.</p>
                        <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                    </div>
                </form>
            </section>
            @endcan

            {{-- ============ Security ============ --}}
            <section x-show="tab === 'security'" x-cloak class="card" aria-labelledby="security-heading">
                <div class="card-header">
                    <div>
                        <h2 id="security-heading" class="card-title">Password</h2>
                        <p class="card-description">Use at least 8 characters. Other sessions stay signed in until they expire.</p>
                    </div>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="key" class="size-4" /></span>
                </div>
                <form method="POST" action="{{ route('studio.settings.password') }}">
                    @csrf
                    <div class="card-body max-w-lg space-y-5">
                        <x-ui.input label="Current password" type="password" name="current_password" required autocomplete="current-password"
                                    :error="$errors->first('current_password')" />
                        <x-ui.input label="New password" type="password" name="password" required autocomplete="new-password"
                                    :error="$errors->first('password')" />
                        <x-ui.input label="Confirm new password" type="password" name="password_confirmation" required autocomplete="new-password" />
                    </div>
                    <div class="card-footer flex items-center justify-end gap-3">
                        <a href="{{ route('password.request') }}" class="mr-auto text-[13px] font-medium text-muted-foreground transition hover:text-foreground">Forgot your password?</a>
                        <button type="submit" class="btn btn-primary">Update password</button>
                    </div>
                </form>
            </section>

            @can('studio.account.manage')
            <section x-show="tab === 'security'" x-cloak class="card mt-6" aria-labelledby="data-heading">
                <div class="card-header">
                    <div>
                        <h2 id="data-heading" class="card-title">Your data</h2>
                        <p class="card-description">Download your profile, galleries, clients, bookings and payments as a JSON file.</p>
                    </div>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="database" class="size-4" /></span>
                </div>
                <div class="card-footer flex justify-end">
                    <a href="{{ route('studio.settings.export') }}" class="btn btn-outline"><x-icon name="download" /> Download my data</a>
                </div>
            </section>
            @endcan

            {{-- Inside a team studio, only members without a studio of their own can delete their login from here --}}
            @if(!$teamContext || !$me->hasOwnStudio())
            <section x-show="tab === 'security'" x-cloak class="card mt-6 border-destructive/40" aria-labelledby="delete-heading">
                <div class="card-header">
                    <div>
                        <h2 id="delete-heading" class="card-title text-destructive">Delete account</h2>
                        <p class="card-description">Your public site goes offline immediately. Galleries, photos and client data are permanently erased after 30 days.</p>
                    </div>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-destructive/10 text-destructive"><x-icon name="trash-2" class="size-4" /></span>
                </div>
                <form method="POST" action="{{ route('studio.settings.account.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <div class="card-body max-w-lg space-y-5">
                        <x-ui.input label="Password" type="password" name="delete_password" required autocomplete="current-password"
                                    :error="$errors->first('delete_password')" />
                        <x-ui.input label="Type DELETE to confirm" name="confirm" required autocomplete="off"
                                    :error="$errors->first('confirm')" />
                    </div>
                    <div class="card-footer flex justify-end">
                        <button type="submit" class="btn btn-danger">Delete my account</button>
                    </div>
                </form>
            </section>
            @endif

            {{-- ============ Notifications ============ --}}
            @unless($teamContext)
            <section x-show="tab === 'notifications'" x-cloak class="card" aria-labelledby="notif-heading">
                <div class="card-header">
                    <div>
                        <h2 id="notif-heading" class="card-title">Email notifications</h2>
                        <p class="card-description">Choose which activity sends an email to <span class="font-medium text-foreground">{{ $user->email }}</span>.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('studio.settings.notifications') }}">
                    @csrf
                    @php
                        $notifValues = \App\Enums\NotificationPreference::resolvedFor($user);
                        $notifRows = collect(\App\Enums\NotificationPreference::cases())->map(fn ($p) => [
                            'name' => $p->value, 'icon' => $p->icon(), 'title' => $p->title(), 'desc' => $p->description(),
                        ]);
                    @endphp
                    <div class="divide-y divide-border border-y border-border">
                        @foreach($notifRows as $row)
                            <label for="notif-{{ $row['name'] }}" class="flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/40">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground"><x-icon :name="$row['icon']" class="size-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-foreground">{{ $row['title'] }}</span>
                                    <span class="block text-[13px] text-muted-foreground">{{ $row['desc'] }}</span>
                                </span>
                                <span class="switch">
                                    <input type="checkbox" id="notif-{{ $row['name'] }}" name="{{ $row['name'] }}" value="1" class="sr-only" {{ $notifValues[$row['name']] ? 'checked' : '' }}>
                                    <span></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="card-footer flex items-center justify-end">
                        <button type="submit" class="btn btn-primary">Save preferences</button>
                    </div>
                </form>
            </section>
            @endunless

            {{-- Plan shortcut (mobile + quick glance) --}}
            @can('studio.billing')
            <a href="{{ route('studio.billing.index') }}" class="card card-hover flex items-center gap-4 p-4 sm:p-5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand"><x-icon name="crown" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-foreground">{{ $plan->name ?? 'Free' }} plan</span>
                    @php
                        $usedGb = round(($storage['used_bytes'] ?? 0) / (1024 ** 3), 2);
                        $limitB = $storage['limit_bytes'] ?? null;
                        $limitGb = $limitB ? $limitB / (1024 ** 3) : null;
                        $limitLabel = $limitGb ? ($limitGb >= 1000 ? rtrim(rtrim(number_format($limitGb / 1000, 1), '0'), '.') . ' TB' : round($limitGb) . ' GB') : 'Unlimited';
                    @endphp
                    <span class="block text-[13px] tabular-nums text-muted-foreground">{{ $usedGb }} GB of {{ $limitLabel }} used · Manage billing and receipts</span>
                </span>
                <x-icon name="chevron-right" class="size-4 text-muted-foreground" />
            </a>
            @endcan
        </div>
    </div>
</div>

<script>
function avatarUploader(config) {
    return {
        preview: config.current || null,
        uploading: false,
        progress: 0,
        statusText: 'Uploading…',
        error: null,

        async sha256Hex(file) {
            const buffer = await file.arrayBuffer();
            const hash = await crypto.subtle.digest('SHA-256', buffer);
            return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
        },

        headers() {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            };
        },

        async pick(event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            if (!file) return;
            this.error = null;

            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                this.error = 'Please choose a JPG, PNG or WebP image.';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                this.error = 'That image is larger than 10 MB.';
                return;
            }

            const previous = this.preview;
            const localUrl = URL.createObjectURL(file);
            this.preview = localUrl;
            this.uploading = true;
            this.progress = 5;
            this.statusText = 'Preparing…';

            try {
                const sha256 = await this.sha256Hex(file);
                const reqRes = await fetch(config.requestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: this.headers(),
                    body: JSON.stringify({ filename: file.name, sha256 }),
                });
                const reqData = await reqRes.json().catch(() => ({}));
                if (!reqRes.ok || !reqData.presigned_url) {
                    throw new Error(reqData.message || 'Could not start the upload.');
                }

                this.statusText = 'Uploading…';
                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', reqData.presigned_url, true);
                    if (!(reqData.headers || {})['Content-Type']) xhr.setRequestHeader('Content-Type', file.type);
                    for (const [k, v] of Object.entries(reqData.headers || {})) {
                        try { xhr.setRequestHeader(k, v); } catch (_) {}
                    }
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) this.progress = Math.max(5, Math.round((e.loaded / e.total) * 90));
                    };
                    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed (' + xhr.status + ').'));
                    xhr.onerror = () => reject(new Error('Network error while uploading.'));
                    xhr.send(file);
                });

                this.statusText = 'Processing…';
                this.progress = 95;
                const confRes = await fetch(config.confirmUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: this.headers(),
                    body: JSON.stringify({ object_key: reqData.object_key }),
                });
                const confData = await confRes.json().catch(() => ({}));
                if (!confRes.ok) {
                    throw new Error(confData.message || 'Could not save your photo.');
                }

                this.progress = 100;
                if (confData.avatar_url) {
                    this.preview = confData.avatar_url;
                    URL.revokeObjectURL(localUrl);
                }
                window.toast && window.toast('Profile photo updated', 'success');
            } catch (e) {
                this.preview = previous;
                URL.revokeObjectURL(localUrl);
                this.error = e.message || 'Upload failed. Please try again.';
                window.toast && window.toast(this.error, 'error');
            } finally {
                this.uploading = false;
                this.progress = 0;
            }
        },
    };
}
</script>
@endsection
