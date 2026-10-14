@extends('layouts.admin', ['title' => 'Settings · Admin'])

@section('content')
@php
    $forcedTab = (session('email_otp_sent') || $errors->has('current_password') || $errors->has('password') || $errors->has('new_email') || $errors->has('code'))
        ? 'security'
        : ($errors->hasAny(['host', 'port', 'username', 'encryption', 'from_address', 'from_name', 'test_email'])
            ? 'smtp'
            : ($errors->hasAny(['platform_name', 'support_email', 'support_phone', 'default_currency', 'momo_merchant_code', 'momo_account_name', 'announcement_banner']) ? 'general' : null));
    $sections = [
        'general' => ['label' => 'General', 'icon' => 'globe', 'hint' => 'Brand, support & payments'],
        'smtp' => ['label' => 'Email delivery', 'short' => 'Email', 'icon' => 'mail', 'hint' => 'SMTP server & test'],
        'security' => ['label' => 'Security', 'icon' => 'shield', 'hint' => 'Password & login email'],
    ];
    $flashToast = session('toast');
@endphp

<div x-data="{
        activeTab: @js($forcedTab) || (function () { try { return sessionStorage.getItem('admin-settings-tab'); } catch (e) { return null; } })() || 'general',
        setTab(tab) {
            this.activeTab = tab;
            try { sessionStorage.setItem('admin-settings-tab', tab); } catch (e) {}
        }
     }">
    <x-ui.page-header title="Settings" description="Platform defaults, transactional email delivery and your administrator account." />

    <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10">
        {{-- Section nav --}}
        <nav aria-label="Settings sections" class="lg:sticky lg:top-4 lg:self-start">
            <div class="tabs w-full overflow-x-auto scrollbar-none lg:hidden">
                @foreach($sections as $key => $s)
                    <button type="button" class="tab flex-1 justify-center whitespace-nowrap" :aria-selected="activeTab === @js($key)" @click="setTab(@js($key))">
                        <x-icon :name="$s['icon']" /> {{ $s['short'] ?? $s['label'] }}
                    </button>
                @endforeach
            </div>
            <ul class="hidden space-y-0.5 lg:block">
                @foreach($sections as $key => $s)
                    <li>
                        <button type="button" @click="setTab(@js($key))"
                                :class="activeTab === @js($key) ? 'bg-card text-foreground shadow-xs ring-1 ring-border' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                                class="flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition-colors">
                            <x-icon :name="$s['icon']" class="mt-0.5 size-4" x-bind:class="activeTab === '{{ $key }}' ? 'text-brand' : ''" />
                            <span class="min-w-0">
                                <span class="block text-[13px] font-medium">{{ $s['label'] }}</span>
                                <span class="block text-xs text-muted-foreground">{{ $s['hint'] }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0">
            {{-- ============ General ============ --}}
            <section x-show="activeTab === 'general'" x-cloak class="space-y-6">
                <form method="POST" action="{{ route('admin.settings.general') }}" class="card" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    @method('PUT')
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Brand & support</h2>
                            <p class="card-description mt-0.5">How the platform introduces itself and where customers can reach you.</p>
                        </div>
                    </div>
                    <div class="card-body grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label" for="platform_name">Platform name</label>
                            <input id="platform_name" type="text" name="platform_name" value="{{ old('platform_name', $general['platform_name']) }}" required class="input @error('platform_name') input-error @enderror">
                            @error('platform_name') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="support_email">Support email</label>
                            <div class="input-group">
                                <x-icon name="mail" />
                                <input id="support_email" type="email" name="support_email" value="{{ old('support_email', $general['support_email']) }}" required class="input @error('support_email') input-error @enderror">
                            </div>
                            @error('support_email') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="support_phone">Support phone / WhatsApp</label>
                            <div class="input-group">
                                <x-icon name="phone" />
                                <input id="support_phone" type="text" name="support_phone" value="{{ old('support_phone', $general['support_phone']) }}" placeholder="+250 788 000 000" class="input @error('support_phone') input-error @enderror">
                            </div>
                            @error('support_phone') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-border px-5 pb-1 pt-5">
                        <h3 class="text-[13px] font-semibold text-foreground">Currency & mobile money</h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">Shown to clients when they pay by MTN or Airtel mobile money.</p>
                    </div>
                    <div class="card-body grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="label" for="default_currency">Default currency</label>
                            <select id="default_currency" name="default_currency" class="select">
                                <option value="RWF" {{ old('default_currency', $general['default_currency']) === 'RWF' ? 'selected' : '' }}>RWF · Rwandan franc</option>
                                <option value="USD" {{ old('default_currency', $general['default_currency']) === 'USD' ? 'selected' : '' }}>USD · US dollar</option>
                                <option value="EUR" {{ old('default_currency', $general['default_currency']) === 'EUR' ? 'selected' : '' }}>EUR · Euro</option>
                            </select>
                            <p class="help">Used for studio packages and pricing.</p>
                        </div>
                        <div>
                            <label class="label" for="momo_merchant_code">MoMo merchant code</label>
                            <input id="momo_merchant_code" type="text" name="momo_merchant_code" value="{{ old('momo_merchant_code', $general['momo_merchant_code']) }}" placeholder="182*8*1*123456#" class="input font-mono">
                            <p class="help">Pay code for offline subscriptions.</p>
                        </div>
                        <div>
                            <label class="label" for="momo_account_name">MoMo account name</label>
                            <input id="momo_account_name" type="text" name="momo_account_name" value="{{ old('momo_account_name', $general['momo_account_name']) }}" placeholder="IFOTOSET MEDIA LTD" class="input">
                            <p class="help">Shown during mobile money transfers.</p>
                        </div>
                    </div>

                    <div class="border-t border-border px-5 pb-1 pt-5">
                        <h3 class="text-[13px] font-semibold text-foreground">Announcement</h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">Displayed at the top of every studio dashboard while it has text. Leave empty to hide.</p>
                    </div>
                    <div class="card-body">
                        <label class="sr-only" for="announcement_banner">Announcement banner</label>
                        <div class="input-group">
                            <x-icon name="bell" />
                            <input id="announcement_banner" type="text" name="announcement_banner" value="{{ old('announcement_banner', $general['announcement_banner']) }}"
                                   placeholder="e.g. Scheduled maintenance on Sunday from 02:00 to 03:00 CAT." class="input">
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                            Save general settings
                        </button>
                    </div>
                </form>
            </section>

            {{-- ============ SMTP ============ --}}
            <section x-show="activeTab === 'smtp'" x-cloak class="space-y-6">
                <form method="POST" action="{{ route('admin.settings.smtp') }}" class="card" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    @method('PUT')
                    <div class="card-header">
                        <div class="min-w-0">
                            <h2 class="card-title">SMTP server</h2>
                            <p class="card-description mt-0.5">Used for invitations, booking confirmations, verification codes and download links.</p>
                        </div>
                        @if($smtp['has_password'])
                            <x-ui.badge variant="success" dot class="shrink-0">Configured</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" dot class="shrink-0">Not configured</x-ui.badge>
                        @endif
                    </div>
                    <div class="card-body grid gap-5 sm:grid-cols-6">
                        <div class="sm:col-span-4">
                            <label class="label" for="smtp_host">Host</label>
                            <div class="input-group">
                                <x-icon name="server" />
                                <input id="smtp_host" type="text" name="host" value="{{ old('host', $smtp['host']) }}" required placeholder="smtp.mailgun.org" class="input font-mono text-[13px] @error('host') input-error @enderror">
                            </div>
                            @error('host') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="smtp_port">Port</label>
                            <input id="smtp_port" type="number" name="port" value="{{ old('port', $smtp['port']) }}" required placeholder="587" class="input font-mono tabular-nums @error('port') input-error @enderror">
                            @error('port') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label" for="smtp_username">Username</label>
                            <input id="smtp_username" type="text" name="username" value="{{ old('username', $smtp['username']) }}" required placeholder="postmaster@yourdomain.com" autocomplete="off" class="input @error('username') input-error @enderror">
                            @error('username') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label" for="smtp_password">Password / API secret</label>
                            <div class="input-group">
                                <x-icon name="key" />
                                <input id="smtp_password" type="password" name="password" autocomplete="new-password"
                                       placeholder="{{ $smtp['has_password'] ? '••••••••••••' : 'Enter SMTP password' }}"
                                       class="input">
                            </div>
                            @if($smtp['has_password'])
                                <p class="help">Stored encrypted. Leave blank to keep the current secret.</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" name="encryption" required class="select @error('encryption') input-error @enderror">
                                <option value="tls" {{ old('encryption', $smtp['encryption']) === 'tls' ? 'selected' : '' }}>TLS · port 587</option>
                                <option value="ssl" {{ old('encryption', $smtp['encryption']) === 'ssl' ? 'selected' : '' }}>SSL · port 465</option>
                                <option value="none" {{ old('encryption', $smtp['encryption']) === 'none' ? 'selected' : '' }}>None · port 25</option>
                            </select>
                            @error('encryption') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="smtp_from_address">From address</label>
                            <input id="smtp_from_address" type="email" name="from_address" value="{{ old('from_address', $smtp['from_address']) }}" required placeholder="no-reply@ifotoset.com" class="input @error('from_address') input-error @enderror">
                            @error('from_address') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="smtp_from_name">From name</label>
                            <input id="smtp_from_name" type="text" name="from_name" value="{{ old('from_name', $smtp['from_name']) }}" required placeholder="ifotoset" class="input @error('from_name') input-error @enderror">
                            @error('from_name') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="card-footer justify-between">
                        <p class="hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
                            <x-icon name="lock" class="size-3.5" /> Secrets are encrypted at rest and never rendered in the page.
                        </p>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                            Save SMTP settings
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.settings.smtp.test') }}" class="card" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="flex items-start gap-3 sm:w-64 sm:shrink-0 sm:self-center">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="send" class="size-4" /></span>
                            <div>
                                <h2 class="text-[13px] font-semibold text-foreground">Send a test email</h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">Check delivery with the saved settings.</p>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="label" for="test_email">Recipient</label>
                            <input id="test_email" type="email" name="test_email" required value="{{ auth()->user()->email }}" placeholder="admin@yourdomain.com" class="input @error('test_email') input-error @enderror">
                            @error('test_email') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="btn btn-outline" :disabled="busy">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                            <x-icon name="send" x-show="!busy" />
                            <span x-text="busy ? 'Sending…' : 'Send test'">Send test</span>
                        </button>
                    </div>
                </form>
            </section>

            {{-- ============ Security ============ --}}
            <section x-show="activeTab === 'security'" x-cloak class="space-y-6">
                {{-- Password --}}
                <form method="POST" action="{{ route('admin.settings.password') }}" class="card" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    @method('PUT')
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Password</h2>
                            <p class="card-description mt-0.5">Changing your password signs you out on all other devices.</p>
                        </div>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="lock" class="size-4" /></span>
                    </div>
                    <div class="card-body grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]">
                            <label class="label" for="current_password_pw">Current password</label>
                            <input id="current_password_pw" type="password" name="current_password" required autocomplete="current-password" placeholder="Enter your current password" class="input @error('current_password') input-error @enderror">
                            @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="new_password">New password</label>
                            <input id="new_password" type="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters" class="input @error('password') input-error @enderror">
                            @error('password') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="new_password_confirmation">Confirm new password</label>
                            <input id="new_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat new password" class="input">
                        </div>
                    </div>
                    <div class="card-footer justify-between">
                        <p class="hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
                            <x-icon name="triangle-alert" class="size-3.5 text-warning" /> Other sessions will be logged out.
                        </p>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                            Update password
                        </button>
                    </div>
                </form>

                {{-- Login email with verification code --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Login email</h2>
                            <p class="card-description mt-0.5">We'll send a 6-digit code to the new address to confirm you own it.</p>
                        </div>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="at-sign" class="size-4" /></span>
                    </div>

                    {{-- Stepper --}}
                    <div class="flex items-center gap-3 border-b border-border bg-muted/30 px-5 py-3 text-xs">
                        @php($onStep2 = (bool) session('email_otp_sent'))
                        <span class="inline-flex items-center gap-1.5 {{ $onStep2 ? 'text-muted-foreground' : 'font-medium text-foreground' }}">
                            <span class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold {{ $onStep2 ? 'bg-success/15 text-success' : 'bg-primary text-primary-foreground' }}">
                                @if($onStep2)<x-icon name="check" class="size-3" />@else 1 @endif
                            </span>
                            Request code
                        </span>
                        <span class="h-px w-6 bg-border-strong sm:w-10"></span>
                        <span class="inline-flex items-center gap-1.5 {{ $onStep2 ? 'font-medium text-foreground' : 'text-muted-foreground' }}">
                            <span class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold {{ $onStep2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground ring-1 ring-border' }}">2</span>
                            Verify
                        </span>
                    </div>

                    <div class="card-body space-y-5">
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2.5 text-[13px]">
                            <span class="text-muted-foreground">Current</span>
                            <span class="truncate font-mono text-foreground">{{ auth()->user()->email }}</span>
                        </div>

                        @if(!session('email_otp_sent'))
                            <form method="POST" action="{{ route('admin.settings.email.request') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label class="label" for="new_email">New email</label>
                                        <input id="new_email" type="email" name="new_email" value="{{ old('new_email') }}" required placeholder="newadmin@ifotoset.com" class="input @error('new_email') input-error @enderror">
                                        @error('new_email') <p class="error-text">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="label" for="current_password_email">Current password</label>
                                        <input id="current_password_email" type="password" name="current_password" required autocomplete="current-password" placeholder="To authorize the change" class="input @error('current_password') input-error @enderror">
                                        @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                                        <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                                        Send verification code
                                        <x-icon name="arrow-right" x-show="!busy" />
                                    </button>
                                </div>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.settings.email.verify') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                @method('PUT')
                                <div class="alert alert-info">
                                    <x-icon name="mail" />
                                    <p class="text-[13px]">
                                        We sent a 6-digit code to <strong class="font-semibold">{{ session('target_new_email') }}</strong>. It expires in 15 minutes. Check spam if it doesn't arrive.
                                    </p>
                                </div>
                                <div class="flex flex-col items-center">
                                    <label class="label text-center" for="email_code">Verification code</label>
                                    <input id="email_code" type="text" name="code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" autofocus
                                           placeholder="000000"
                                           class="input h-14 w-56 text-center font-mono text-2xl font-semibold tracking-[0.5em] tabular-nums @error('code') input-error @enderror">
                                    @error('code') <p class="error-text text-center">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="arrow-left" /> Use a different email
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                                        <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                                        <x-icon name="shield-check" x-show="!busy" />
                                        Verify & update email
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

@if(is_array($flashToast) && !empty($flashToast['message']))
    @push('scripts')
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.toast?.(@js($flashToast['message']), @js($flashToast['type'] ?? 'info'), 6000);
        });
    </script>
    @endpush
@endif
@endsection
