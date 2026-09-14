@extends('layouts.admin', ['title' => 'Support Desk - Admin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Customer Support &amp; Help Desk</h1>
            <p class="text-xs text-muted-foreground mt-1">Manage inquiries from photographers, studios, and booking clients.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Active Tickets: <span class="font-bold text-foreground">{{ count($tickets) }}</span>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Ticket</th>
                        <th class="px-6 py-4">Subject</th>
                        <th class="px-6 py-4">Photographer / User</th>
                        <th class="px-6 py-4">Priority</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($tickets as $tk)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-foreground">
                                {{ $tk['id'] }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-foreground">{{ $tk['subject'] }}</div>
                                <div class="text-xs text-muted-foreground mt-0.5 line-clamp-1">{{ $tk['message'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="font-medium text-foreground">{{ $tk['user'] }}</div>
                                <div class="text-muted-foreground">{{ $tk['email'] }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $tk['priority'] === 'Urgent' ? 'bg-destructive/10 text-destructive' : ($tk['priority'] === 'Medium' ? 'bg-amber-500/10 text-amber-600' : 'bg-secondary text-foreground') }}">
                                    {{ $tk['priority'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="$tk['status'] === 'Resolved' ? 'success' : ($tk['status'] === 'In Progress' ? 'warning' : 'default')">
                                    {{ $tk['status'] }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground text-right">
                                {{ $tk['date'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                No support tickets currently open.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
