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
                                <div class="flex items-center justify-end gap-2">
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
                                    @else
                                        <span class="text-[11px] text-muted-foreground italic">Your Account</span>
                                    @endif
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
