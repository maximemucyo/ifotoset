@extends('layouts.app', ['title' => 'Clients CRM - Studio'])

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    editModalOpen: false,
    selectedClient: null,
    openEdit(client) {
        this.selectedClient = client;
        this.editModalOpen = true;
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Client Directory</h1>
            <p class="text-xs text-muted-foreground mt-1">Manage client profiles, contact information, and session histories.</p>
        </div>
        <button type="button"
                @click="createModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90 transition-opacity">
            + Add Client
        </button>
    </div>

    <!-- Search & Summary -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" action="{{ route('studio.clients.index') }}" class="w-full sm:w-80">
            <div class="relative">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by name, email, or phone..."
                       class="w-full rounded-xl border border-border bg-card px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                @if($search)
                    <a href="{{ route('studio.clients.index') }}" class="absolute right-3 top-2.5 text-xs text-muted-foreground hover:text-foreground">
                        &times;
                    </a>
                @endif
            </div>
        </form>

        <div class="text-xs text-muted-foreground">
            Total Clients: <span class="font-bold text-foreground">{{ $clients->total() }}</span>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Client Name</th>
                        <th class="px-6 py-4">Contact Details</th>
                        <th class="px-6 py-4">Company / Location</th>
                        <th class="px-6 py-4">Bookings</th>
                        <th class="px-6 py-4">Added</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($clients as $client)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-foreground">{{ $client->name }}</div>
                                @if($client->instagram)
                                    <div class="text-[11px] text-muted-foreground font-mono">&#64;{{ ltrim($client->instagram, '@') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($client->email)
                                    <div class="text-foreground">{{ $client->email }}</div>
                                @endif
                                @if($client->phone)
                                    <div class="text-muted-foreground font-mono mt-0.5">{{ $client->phone }}</div>
                                @endif
                                @if(!$client->email && !$client->phone)
                                    <span class="text-muted-foreground italic">No contact info</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($client->company_name)
                                    <div class="font-medium text-foreground">{{ $client->company_name }}</div>
                                @endif
                                @if($client->location)
                                    <div class="text-muted-foreground">{{ $client->location }}</div>
                                @endif
                                @if(!$client->company_name && !$client->location)
                                    <span class="text-muted-foreground">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-secondary text-foreground">
                                    {{ $client->bookings_count }} sessions
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $client->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button"
                                            @click="openEdit({{ json_encode([
                                                'uuid' => $client->uuid,
                                                'name' => $client->name,
                                                'email' => $client->email,
                                                'phone' => $client->phone,
                                                'company_name' => $client->company_name,
                                                'location' => $client->location,
                                                'instagram' => $client->instagram,
                                                'notes' => $client->notes,
                                            ]) }})"
                                            class="text-xs font-semibold text-primary hover:underline">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('studio.clients.destroy', $client->uuid) }}" onsubmit="return confirm('Delete client {{ addslashes($client->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-muted-foreground hover:text-destructive transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                @if($search)
                                    No clients matching "{{ $search }}".
                                @else
                                    No clients in your directory yet. Click "+ Add Client" to get started.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $clients->links() }}
    </div>

    <!-- Create Client Modal -->
    <div x-show="createModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="createModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-card border border-border p-6 shadow-2xl text-left">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Add New Client</h3>
                    <button type="button" @click="createModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('studio.clients.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Full Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Diane Mukamisha" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Email</label>
                            <input type="email" name="email" placeholder="diane@example.com" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Phone / WhatsApp</label>
                            <input type="text" name="phone" placeholder="+250 788 000 000" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Company / Organization</label>
                            <input type="text" name="company_name" placeholder="e.g. Rwanda Fashion Week" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Location / City</label>
                            <input type="text" name="location" placeholder="Kigali, Rwanda" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Instagram Handle</label>
                        <input type="text" name="instagram" placeholder="@username" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Internal Notes</label>
                        <textarea name="notes" rows="3" placeholder="Preferences, shoot style, reminders..." class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Save Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <div x-show="editModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-card border border-border p-6 shadow-2xl text-left">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Edit Client Profile</h3>
                    <button type="button" @click="editModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" :action="'/studio/clients/' + (selectedClient?.uuid ?? '')" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Full Name *</label>
                        <input type="text" name="name" required :value="selectedClient?.name" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Email</label>
                            <input type="email" name="email" :value="selectedClient?.email" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Phone / WhatsApp</label>
                            <input type="text" name="phone" :value="selectedClient?.phone" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Company / Organization</label>
                            <input type="text" name="company_name" :value="selectedClient?.company_name" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Location / City</label>
                            <input type="text" name="location" :value="selectedClient?.location" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Instagram Handle</label>
                        <input type="text" name="instagram" :value="selectedClient?.instagram" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Internal Notes</label>
                        <textarea name="notes" rows="3" x-text="selectedClient?.notes" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
