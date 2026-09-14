@extends('layouts.admin', ['title' => 'Platform Settings - Admin'])

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'smtp' }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Platform & Email Settings</h1>
            <p class="text-xs text-muted-foreground mt-1">Configure transactional mail delivery, security credentials, and regional media house defaults.</p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="activeTab = 'smtp'"
                    :class="activeTab === 'smtp' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'bg-secondary/60 text-muted-foreground hover:text-foreground'"
                    class="px-4 py-2 rounded-xl text-xs transition-colors">
                SMTP Configuration
            </button>
            <button @click="activeTab = 'general'"
                    :class="activeTab === 'general' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'bg-secondary/60 text-muted-foreground hover:text-foreground'"
                    class="px-4 py-2 rounded-xl text-xs transition-colors">
                Platform & Regional Defaults
            </button>
        </div>
    </div>

    <!-- SMTP Configuration Section -->
    <div x-show="activeTab === 'smtp'" class="space-y-6">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- SMTP Settings Form (2 columns) -->
            <div class="lg:col-span-2 rounded-2xl border border-border bg-card p-6 shadow-sm">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-border">
                    <div>
                        <h2 class="text-base font-bold text-foreground">Outbound Mail Server (SMTP)</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Transactional emails, client invitations, booking confirmations, and ZIP download links.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $smtp['has_password'] ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 border border-amber-500/20' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $smtp['has_password'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ $smtp['has_password'] ? 'Configured & Encrypted' : 'Unconfigured' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.settings.smtp') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">SMTP Host</label>
                            <input type="text" name="host" value="{{ old('host', $smtp['host']) }}" required
                                   placeholder="smtp.mailgun.org or email-smtp.us-east-1.amazonaws.com"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('host') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">SMTP Port</label>
                            <input type="number" name="port" value="{{ old('port', $smtp['port']) }}" required
                                   placeholder="587"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('port') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">SMTP Username</label>
                            <input type="text" name="username" value="{{ old('username', $smtp['username']) }}" required
                                   placeholder="postmaster@yourdomain.com"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('username') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">
                                SMTP Password / API Secret
                                @if($smtp['has_password'])
                                    <span class="text-muted-foreground font-normal">(Encrypted - leave blank to keep unchanged)</span>
                                @endif
                            </label>
                            <input type="password" name="password"
                                   placeholder="{{ $smtp['has_password'] ? '••••••••••••' : 'Enter SMTP password' }}"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('password') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Encryption Protocol</label>
                            <select name="encryption" required class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="tls" {{ old('encryption', $smtp['encryption']) === 'tls' ? 'selected' : '' }}>TLS (Port 587)</option>
                                <option value="ssl" {{ old('encryption', $smtp['encryption']) === 'ssl' ? 'selected' : '' }}>SSL (Port 465)</option>
                                <option value="none" {{ old('encryption', $smtp['encryption']) === 'none' ? 'selected' : '' }}>None (Port 25)</option>
                            </select>
                            @error('encryption') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">From Email Address</label>
                            <input type="email" name="from_address" value="{{ old('from_address', $smtp['from_address']) }}" required
                                   placeholder="no-reply@ifotoset.com"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('from_address') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">From Sender Name</label>
                            <input type="text" name="from_name" value="{{ old('from_name', $smtp['from_name']) }}" required
                                   placeholder="ifotoset"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('from_name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-primary-foreground font-semibold text-xs hover:bg-primary/90 transition-colors shadow-sm">
                            Save SMTP Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- SMTP Live Verification Panel -->
            <div class="space-y-6">
                <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h2 class="text-base font-bold text-foreground">Send Test Email</h2>
                    <p class="text-xs text-muted-foreground mt-1 mb-4">Validate that outbound mail delivery is functioning before notifying photographers or clients.</p>

                    <form method="POST" action="{{ route('admin.settings.smtp.test') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Recipient Email</label>
                            <input type="email" name="test_email" required
                                   value="{{ auth()->user()->email }}"
                                   placeholder="admin@yourdomain.com"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            @error('test_email') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="w-full px-4 py-2.5 rounded-xl border border-primary text-primary hover:bg-primary/10 font-semibold text-xs transition-colors">
                            Send Live Test Email
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-border bg-secondary/30 p-5 text-xs text-muted-foreground space-y-2">
                    <p class="font-semibold text-foreground">Security Note</p>
                    <p>SMTP passwords are encrypted at rest using AES-256-GCM via Laravel's encryption engine and are never exposed in browser HTML markup.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform & Regional Defaults Section -->
    <div x-show="activeTab === 'general'" class="space-y-6" style="display: none;">
        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <div class="pb-4 mb-6 border-b border-border">
                <h2 class="text-base font-bold text-foreground">Platform Defaults & Regional Support</h2>
                <p class="text-xs text-muted-foreground mt-0.5">Branding, regional currency (RWF), local Rwandan mobile money configuration, and customer support channels.</p>
            </div>

            <form method="POST" action="{{ route('admin.settings.general') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid sm:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Platform Brand Name</label>
                        <input type="text" name="platform_name" value="{{ old('platform_name', $general['platform_name']) }}" required
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        @error('platform_name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Support Email Address</label>
                        <input type="email" name="support_email" value="{{ old('support_email', $general['support_email']) }}" required
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        @error('support_email') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Support Hotline / WhatsApp</label>
                        <input type="text" name="support_phone" value="{{ old('support_phone', $general['support_phone']) }}"
                               placeholder="+250 788 000 000"
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        @error('support_phone') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-6 pt-4 border-t border-border">
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Default Platform Currency</label>
                        <select name="default_currency" class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="RWF" {{ old('default_currency', $general['default_currency']) === 'RWF' ? 'selected' : '' }}>RWF (Rwandan Franc)</option>
                            <option value="USD" {{ old('default_currency', $general['default_currency']) === 'USD' ? 'selected' : '' }}>USD ($)</option>
                            <option value="EUR" {{ old('default_currency', $general['default_currency']) === 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                        </select>
                        <p class="text-[11px] text-muted-foreground mt-1">Used across media houses and studio pricing packages.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">MTN / Airtel MoMo Merchant Code</label>
                        <input type="text" name="momo_merchant_code" value="{{ old('momo_merchant_code', $general['momo_merchant_code']) }}"
                               placeholder="e.g. 182*8*1*123456#"
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        <p class="text-[11px] text-muted-foreground mt-1">Direct MoMo pay code for media house subscriptions & offline payments.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">MoMo Registered Account Name</label>
                        <input type="text" name="momo_account_name" value="{{ old('momo_account_name', $general['momo_account_name']) }}"
                               placeholder="IFOTOSET MEDIA LTD"
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        <p class="text-[11px] text-muted-foreground mt-1">Displayed to clients when completing mobile money transfers.</p>
                    </div>
                </div>

                <div class="space-y-4 pt-4 border-t border-border">
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Broadcast Announcement Banner</label>
                        <input type="text" name="announcement_banner" value="{{ old('announcement_banner', $general['announcement_banner']) }}"
                               placeholder="e.g., Welcome Rwandan Media Houses! High-resolution B2 delivery is now active across Kigali."
                               class="w-full px-3 py-2 text-sm rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        <p class="text-[11px] text-muted-foreground mt-1">Visible to all studios at the top of their dashboard when populated.</p>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-primary-foreground font-semibold text-xs hover:bg-primary/90 transition-colors shadow-sm">
                        Save Platform Defaults
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
