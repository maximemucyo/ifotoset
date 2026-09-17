@extends('layouts.admin', ['title' => 'Users Management - Admin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">User Management &amp; Tenants</h1>
            <p class="text-xs text-muted-foreground mt-1">Directory of all registered photographers, clients, and platform administrators.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Total Users: <span class="font-bold text-foreground">{{ $users->total() }}</span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="w-full sm:w-80">
            <div class="relative">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by name, email, or username..."
                       class="w-full rounded-xl border border-border bg-card px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                @if($search)
                    <a href="{{ route('admin.users.index', array_filter(['role' => $role])) }}" class="absolute right-3 top-2.5 text-xs text-muted-foreground hover:text-foreground">
                        &times;
                    </a>
                @endif
            </div>
        </form>

        <div class="flex items-center gap-2 text-xs font-medium">
            <a href="{{ route('admin.users.index', array_filter(['search' => $search])) }}"
               class="px-3 py-1.5 rounded-lg {{ empty($role) ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                All Accounts
            </a>
            <a href="{{ route('admin.users.index', array_filter(['search' => $search, 'role' => 'user'])) }}"
               class="px-3 py-1.5 rounded-lg {{ $role === 'user' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                Photographers
            </a>
            <a href="{{ route('admin.users.index', array_filter(['search' => $search, 'role' => 'admin'])) }}"
               class="px-3 py-1.5 rounded-lg {{ $role === 'admin' ? 'bg-secondary text-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                Admins
            </a>
        </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Role</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Plan</th>
                        <th class="px-6 py-4">Galleries</th>
                        <th class="px-6 py-4">Storage</th>
                        <th class="px-6 py-4">Joined</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($users as $user)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-foreground">{{ $user->name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ $user->email }}
                                    @if($user->username)
                                        &bull; <a href="{{ $user->public_url }}" target="_blank" class="text-primary hover:underline font-mono">&#64;{{ $user->username }}</a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="$user->role === 'admin' ? 'destructive' : 'muted'">
                                    {{ ucfirst($user->role ?? 'user') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <x-ui.badge :variant="$user->is_active ? 'success' : 'destructive'">
                                    {{ $user->is_active ? 'Active' : 'Suspended' }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-foreground">
                                {{ ucfirst($user->plan->name ?? 'Free') }}
                            </td>
                            <td class="px-6 py-4 text-xs text-foreground font-semibold">
                                {{ $user->galleries_count }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ round($user->storage_used_bytes / (1024 * 1024), 1) }} MB
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $user->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2" x-data="{ planModalOpen: false, revokeModalOpen: false }">
                                    <!-- Plan Badge / Quick Trigger -->
                                    <button type="button"
                                            @click="planModalOpen = true"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                        Change Plan
                                    </button>

                                    <!-- Revoke trigger if on paid plan -->
                                    @if($user->plan && $user->plan->slug !== 'free')
                                        <button type="button"
                                                @click="revokeModalOpen = true"
                                                class="px-2 py-1 text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 rounded-lg transition-colors border border-amber-500/20">
                                            Revoke
                                        </button>
                                    @endif

                                    <!-- Toggle Active / Suspended -->
                                    @if(auth()->id() !== $user->id)
                                        <form method="POST" action="{{ route('admin.users.status', $user->id) }}" onsubmit="return confirm('{{ $user->is_active ? 'Suspend this account?' : 'Activate this account?' }}');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg {{ $user->is_active ? 'bg-destructive/10 text-destructive hover:bg-destructive/20' : 'bg-green-500/10 text-green-600 hover:bg-green-500/20' }} transition-colors">
                                                {{ $user->is_active ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </form>

                                        <!-- Toggle Role -->
                                        <form method="POST" action="{{ route('admin.users.role', $user->id) }}" onsubmit="return confirm('Change role for {{ addslashes($user->name) }} to {{ $user->role === 'admin' ? 'user' : 'admin' }}?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="role" value="{{ $user->role === 'admin' ? 'user' : 'admin' }}">
                                            <button type="submit" class="px-2.5 py-1 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors border border-border rounded-lg">
                                                {{ $user->role === 'admin' ? 'Demote' : 'Make Admin' }}
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Change Plan Modal -->
                                    <div x-show="planModalOpen"
                                         style="display: none;"
                                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-foreground/30 backdrop-blur-sm">
                                        <div @click.away="planModalOpen = false" class="bg-card border border-border rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 text-left">
                                            <div>
                                                <h3 class="text-base font-bold text-foreground">Change Plan for {{ $user->name }}</h3>
                                                <p class="text-xs text-muted-foreground mt-0.5">Current: <strong class="text-foreground">{{ $user->plan->name ?? 'Free' }}</strong> ({{ round($user->storage_used_bytes / (1024 * 1024), 1) }} MB used)</p>
                                            </div>

                                            <form method="POST" action="{{ route('admin.users.plan', $user->id) }}" class="space-y-4">
                                                @csrf
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Select New Plan Tier</label>
                                                    <select name="plan_slug" class="w-full rounded-xl border border-border bg-input px-3 py-2 text-xs font-medium text-foreground">
                                                        @foreach($plans as $p)
                                                            @php
                                                                $pLimit = $p->storage_limit;
                                                                $pGb = ($pLimit % 1000000000 === 0 && ($pLimit % (1024 * 1024) !== 0))
                                                                    ? round($pLimit / 1000000000, 0)
                                                                    : round($pLimit / (1024 * 1024 * 1024), 0);
                                                            @endphp
                                                            <option value="{{ $p->slug }}" {{ $user->plan_id === $p->id ? 'selected' : '' }}>
                                                                {{ $p->name }} ({{ $pGb >= 1000 ? round($pGb / 1000, 1) . ' TB' : $pGb . ' GB' }}, Unlimited Galleries)
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Billing Cycle</label>
                                                    <select name="billing_cycle" class="w-full rounded-xl border border-border bg-input px-3 py-2 text-xs font-medium text-foreground">
                                                        <option value="monthly">Monthly</option>
                                                        <option value="annual">Annual (12 Months)</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Reason / Note (Audit Log)</label>
                                                    <input type="text" name="reason" placeholder="e.g. VIP promotion / manual override" class="w-full rounded-xl border border-border bg-input px-3 py-2 text-xs text-foreground">
                                                </div>

                                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                                                    <button type="button" @click="planModalOpen = false" class="px-3 py-2 rounded-xl text-xs font-semibold text-muted-foreground hover:text-foreground">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm">
                                                        Apply Plan Change
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Revoke Plan Confirmation Modal -->
                                    <div x-show="revokeModalOpen"
                                         style="display: none;"
                                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-foreground/30 backdrop-blur-sm">
                                        <div @click.away="revokeModalOpen = false" class="bg-card border border-border rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 text-left">
                                            <div>
                                                <h3 class="text-base font-bold text-destructive">Revoke {{ $user->plan->name ?? 'Paid' }} Plan?</h3>
                                                <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
                                                    User <strong class="text-foreground">{{ $user->name }}</strong> will immediately return to <strong>Free Tier (2 GB)</strong>.
                                                </p>
                                            </div>

                                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-300">
                                                <strong>Notice:</strong> Existing files above 2 GB will <u>NOT</u> be deleted. However, new uploads will be blocked until storage usage falls below quota or the plan is upgraded.
                                            </div>

                                            <form method="POST" action="{{ route('admin.users.plan.revoke', $user->id) }}" class="space-y-4">
                                                @csrf
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Reason for Revocation</label>
                                                    <input type="text" name="reason" required placeholder="e.g. Subscription ended / chargeback" class="w-full rounded-xl border border-border bg-input px-3 py-2 text-xs text-foreground">
                                                </div>

                                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                                                    <button type="button" @click="revokeModalOpen = false" class="px-3 py-2 rounded-xl text-xs font-semibold text-muted-foreground hover:text-foreground">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors shadow-sm">
                                                        Confirm Revoke
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-muted-foreground">
                                No users found matching this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
