@extends('layouts.app', ['title' => 'Settings - Studio'])

@section('content')
<div class="max-w-4xl mx-auto space-y-8" x-data="{ tab: 'profile' }">
    <div class="pb-4 border-b border-border">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Studio Settings</h1>
        <p class="text-xs text-muted-foreground mt-1">Manage your public photographer profile, security, and account preferences.</p>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-2 border-b border-border pb-4 text-xs font-medium">
        <button type="button" @click="tab = 'profile'" :class="tab === 'profile' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'" class="px-4 py-2 rounded-lg transition-colors">
            Profile Details
        </button>
        <button type="button" @click="tab = 'security'" :class="tab === 'security' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'" class="px-4 py-2 rounded-lg transition-colors">
            Security &amp; Password
        </button>
        <button type="button" @click="tab = 'notifications'" :class="tab === 'notifications' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'" class="px-4 py-2 rounded-lg transition-colors">
            Email Notifications
        </button>
        <button type="button" @click="tab = 'plan'" :class="tab === 'plan' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground'" class="px-4 py-2 rounded-lg transition-colors">
            Subscription &amp; Storage
        </button>
    </div>

    <!-- TAB 1: Profile -->
    <div x-show="tab === 'profile'" class="space-y-6">
        <div class="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm">
            <h2 class="text-lg font-bold text-foreground mb-6">Photographer Profile</h2>

            <form method="POST" action="{{ route('studio.settings.profile') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid sm:grid-cols-2 gap-6">
                    <x-ui.input label="Display Name"
                                name="name"
                                required
                                :value="old('name', $user->name)"
                                :error="$errors->first('name')" />

                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Username (Portfolio Slug)</label>
                        <div class="flex rounded-lg border border-border bg-muted/40 shadow-sm overflow-hidden text-sm">
                            <span class="inline-flex items-center px-3 text-muted-foreground bg-muted border-r border-border text-xs font-mono">
                                ifotoset.com/p/
                            </span>
                            <input type="text"
                                   disabled
                                   value="{{ $user->username }}"
                                   class="block w-full bg-transparent px-3 py-2 text-muted-foreground font-mono text-xs cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="bio" class="block text-sm font-medium text-foreground">Bio / About You</label>
                    <textarea name="bio" id="bio" rows="4" class="block w-full rounded-lg border border-border bg-input px-3.5 py-2 text-foreground text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Tell clients about your photography style and experience...">{{ old('bio', $user->bio) }}</textarea>
                    @if($errors->has('bio'))
                        <p class="text-xs text-destructive mt-1">{{ $errors->first('bio') }}</p>
                    @endif
                </div>

                <div class="grid sm:grid-cols-3 gap-6">
                    <x-ui.input label="City / Location"
                                name="location"
                                placeholder="Kigali, Rwanda"
                                :value="old('location', $user->location)" />

                    <x-ui.input label="Website URL"
                                type="url"
                                name="website"
                                placeholder="https://myphotos.com"
                                :value="old('website', $user->website)" />

                    <x-ui.input label="Phone / WhatsApp"
                                name="phone"
                                placeholder="+250 788 000 000"
                                :value="old('phone', $user->phone)" />
                </div>

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" variant="primary">
                        Save Profile Changes
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: Security & Password -->
    <div x-show="tab === 'security'" class="space-y-6" style="display: none;">
        <div class="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm">
            <h2 class="text-lg font-bold text-foreground mb-6">Security &amp; Password</h2>

            <form method="POST" action="{{ route('studio.settings.password') }}" class="space-y-6 max-w-xl">
                @csrf
                @method('PUT')

                <x-ui.input label="Current Password"
                            type="password"
                            name="current_password"
                            required
                            :error="$errors->first('current_password')" />

                <x-ui.input label="New Password"
                            type="password"
                            name="password"
                            required
                            :error="$errors->first('password')" />

                <x-ui.input label="Confirm New Password"
                            type="password"
                            name="password_confirmation"
                            required />

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" variant="primary">
                        Update Password
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 3: Notifications -->
    <div x-show="tab === 'notifications'" class="space-y-6" style="display: none;">
        <div class="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm">
            <h2 class="text-lg font-bold text-foreground mb-2">Notification Preferences</h2>
            <p class="text-xs text-muted-foreground mb-6">Choose which activities trigger email notifications to your inbox.</p>

            @php
                $prefs = $user->notification_preferences ?? [];
            @endphp
            <form method="POST" action="{{ route('studio.settings.notifications') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-card/60 hover:bg-secondary/20 transition-colors cursor-pointer">
                    <input type="checkbox" name="booking_created" value="1" {{ !empty($prefs['booking_created']) ? 'checked' : '' }} class="mt-0.5 rounded border-border text-primary focus:ring-primary w-4 h-4">
                    <div>
                        <div class="text-sm font-semibold text-foreground">New Booking Request</div>
                        <div class="text-xs text-muted-foreground">Receive an email when a client books a session through your portfolio.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-card/60 hover:bg-secondary/20 transition-colors cursor-pointer">
                    <input type="checkbox" name="photo_downloaded" value="1" {{ !empty($prefs['photo_downloaded']) ? 'checked' : '' }} class="mt-0.5 rounded border-border text-primary focus:ring-primary w-4 h-4">
                    <div>
                        <div class="text-sm font-semibold text-foreground">Gallery Downloads</div>
                        <div class="text-xs text-muted-foreground">Get notified when a client downloads high-resolution photos or a full gallery ZIP.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-card/60 hover:bg-secondary/20 transition-colors cursor-pointer">
                    <input type="checkbox" name="photo_favorited" value="1" {{ !empty($prefs['photo_favorited']) ? 'checked' : '' }} class="mt-0.5 rounded border-border text-primary focus:ring-primary w-4 h-4">
                    <div>
                        <div class="text-sm font-semibold text-foreground">Photo Favorites</div>
                        <div class="text-xs text-muted-foreground">Receive an update when clients star or shortlist photos for proofing.</div>
                    </div>
                </label>

                <div class="flex justify-end pt-4 border-t border-border">
                    <x-ui.button type="submit" variant="primary">
                        Save Preferences
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 4: Plan & Storage -->
    <div x-show="tab === 'plan'" class="space-y-6" style="display: none;">
        <div class="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-foreground">Current Plan &amp; Quota</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Your storage tier and platform allowances</p>
                </div>
                <x-ui.badge variant="default">
                    {{ ucfirst($plan->name ?? 'Free Tier') }}
                </x-ui.badge>
            </div>

            <!-- Storage Progress -->
            <div class="p-4 rounded-xl bg-secondary/30 border border-border space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-foreground">Storage Allocation</span>
                    <span class="font-bold text-primary">{{ round($storage['percentage'] ?? 0) }}% Used</span>
                </div>
                <div class="w-full bg-secondary h-2.5 rounded-full overflow-hidden">
                    <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ min(100, max(2, $storage['percentage'] ?? 0)) }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                    <span>{{ round(($storage['used_bytes'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB used</span>
                    <span>{{ round(($storage['limit_bytes'] ?? (5 * 1024 * 1024 * 1024)) / (1024 * 1024 * 1024)) }} GB available</span>
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-4 text-xs">
                <div class="p-3 rounded-xl border border-border bg-card">
                    <div class="text-muted-foreground">Gallery Limit</div>
                    <div class="text-base font-bold text-foreground mt-1">{{ $plan->gallery_limit ?? 'Unlimited' }}</div>
                </div>
                <div class="p-3 rounded-xl border border-border bg-card">
                    <div class="text-muted-foreground">High-Res Downloads</div>
                    <div class="text-base font-bold text-foreground mt-1">Included</div>
                </div>
                <div class="p-3 rounded-xl border border-border bg-card">
                    <div class="text-muted-foreground">Mobile Money Payments</div>
                    <div class="text-base font-bold text-foreground mt-1">Active</div>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <a href="{{ route('studio.billing.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm">
                    <span>Manage Plans &amp; Upgrade</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
