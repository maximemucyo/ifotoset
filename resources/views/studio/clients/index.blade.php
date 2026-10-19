@extends('layouts.app', ['title' => 'Clients · ifotoset'])

@section('content')
@php
    $totalClients = (int) ($stats['total_clients'] ?? $clients->total());
    $clientPayload = fn ($client) => [
        'uuid' => $client->uuid,
        'name' => $client->name,
        'email' => $client->email,
        'phone' => $client->phone,
        'company_name' => $client->company_name,
        'location' => $client->location,
        'instagram' => $client->instagram,
        'notes' => $client->notes,
    ];
@endphp

<div x-data="{
    createModalOpen: false,
    editModalOpen: false,
    selectedClient: null,
    openEdit(client) {
        this.selectedClient = client;
        this.editModalOpen = true;
    }
}">
    <x-ui.page-header title="Clients" description="Everyone you've photographed or are about to, with contact details and session history in one place.">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
                <span class="inline-flex items-center gap-1.5"><x-icon name="users" class="size-3.5" /> <span class="font-medium tabular-nums text-foreground">{{ number_format($totalClients) }}</span> {{ \Illuminate\Support\Str::plural('client', $totalClients) }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('studio.bookings.index') }}" class="btn btn-outline"><x-icon name="calendar" /> Bookings</a>
            <button type="button" @click="createModalOpen = true" class="btn btn-primary">
                <x-icon name="user-plus" /> Add client
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($errors->any())
        <div class="alert alert-danger mb-6">
            <x-icon name="circle-alert" />
            <div>
                <p class="font-medium">Please check the client details.</p>
                <p class="mt-0.5 text-[13px]">{{ $errors->first() }}</p>
            </div>
        </div>
    @endif

    @if($totalClients === 0 && !$search)
        <x-ui.empty-state icon="users"
            title="Your client list is empty"
            description="Add the people you work with to keep their contact details, notes and booking history together. Clients who book online are added automatically."
            action-label="Add your first client" action-click="createModalOpen = true" />
    @else
        <div class="card md:overflow-hidden">
            {{-- Toolbar --}}
            <div class="flex flex-col gap-3 border-b border-border p-3 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <form method="GET" action="{{ route('studio.clients.index') }}" class="w-full sm:max-w-xs" role="search">
                    <div class="input-group">
                        <x-icon name="search" class="input-icon" />
                        <input type="search"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search name, email, phone…"
                               aria-label="Search clients"
                               class="input input-sm pr-8">
                        @if($search)
                            <a href="{{ route('studio.clients.index') }}" class="absolute right-1.5 flex size-6 items-center justify-center rounded-md text-subtle transition hover:bg-muted hover:text-foreground" aria-label="Clear search">
                                <x-icon name="x" class="size-3.5" />
                            </a>
                        @endif
                    </div>
                </form>
                <p class="text-[13px] text-muted-foreground">
                    @if($search)
                        <span class="tabular-nums font-medium text-foreground">{{ $clients->total() }}</span> {{ \Illuminate\Support\Str::plural('result', $clients->total()) }} for “{{ $search }}”
                    @elseif($clients->total() > 0)
                        Showing <span class="font-medium tabular-nums text-foreground">{{ $clients->firstItem() }}–{{ $clients->lastItem() }}</span> of <span class="tabular-nums">{{ $clients->total() }}</span>
                    @endif
                </p>
            </div>

            @if($clients->isEmpty())
                <div class="px-6 py-14 text-center">
                    <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground">
                        <x-icon name="search" class="size-4" />
                    </div>
                    <p class="text-sm font-medium text-foreground">No clients match “{{ $search }}”</p>
                    <p class="mt-1 text-[13px] text-muted-foreground">Try a different name, email or phone number.</p>
                    <a href="{{ route('studio.clients.index') }}" class="btn btn-outline btn-sm mt-4">Clear search</a>
                </div>
            @else
                {{-- Desktop table --}}
                <div class="table-wrap hidden md:block">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th class="hidden lg:table-cell">Company & location</th>
                                <th>Sessions</th>
                                <th class="hidden xl:table-cell">Added</th>
                                <th class="text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clients as $client)
                                <tr class="group">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar :name="$client->name" size="md" />
                                            <div class="min-w-0">
                                                <button type="button" @click="openEdit({{ json_encode($clientPayload($client)) }})" class="block max-w-[220px] truncate text-left font-medium text-foreground hover:underline">{{ $client->name }}</button>
                                                @if($client->instagram)
                                                    <a href="https://instagram.com/{{ ltrim($client->instagram, '@') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs text-muted-foreground transition hover:text-foreground">
                                                        <x-icon name="instagram" class="size-3" />{{ ltrim($client->instagram, '@') }}
                                                    </a>
                                                @elseif($client->notes)
                                                    <p class="max-w-[220px] truncate text-xs text-muted-foreground" title="{{ $client->notes }}">{{ $client->notes }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-[13px]">
                                        @if($client->email)
                                            <a href="mailto:{{ $client->email }}" class="flex max-w-[240px] items-center gap-1.5 truncate text-foreground hover:underline"><x-icon name="mail" class="size-3.5 text-subtle" /><span class="truncate">{{ $client->email }}</span></a>
                                        @endif
                                        @if($client->phone)
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" class="mt-0.5 flex items-center gap-1.5 tabular-nums text-muted-foreground hover:text-foreground"><x-icon name="phone" class="size-3.5 text-subtle" />{{ $client->phone }}</a>
                                        @endif
                                        @if(!$client->email && !$client->phone)
                                            <span class="text-subtle">No contact info</span>
                                        @endif
                                    </td>
                                    <td class="hidden text-[13px] lg:table-cell">
                                        @if($client->company_name)
                                            <div class="max-w-[200px] truncate font-medium text-foreground">{{ $client->company_name }}</div>
                                        @endif
                                        @if($client->location)
                                            <div class="flex max-w-[200px] items-center gap-1 truncate text-muted-foreground"><x-icon name="map-pin" class="size-3" />{{ $client->location }}</div>
                                        @endif
                                        @if(!$client->company_name && !$client->location)
                                            <span class="text-subtle">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($client->bookings_count > 0)
                                            <x-ui.badge variant="neutral"><x-icon name="calendar-check" /> {{ $client->bookings_count }} {{ \Illuminate\Support\Str::plural('session', $client->bookings_count) }}</x-ui.badge>
                                        @else
                                            <span class="text-xs text-subtle">None yet</span>
                                        @endif
                                    </td>
                                    <td class="hidden whitespace-nowrap text-[13px] text-muted-foreground xl:table-cell" title="{{ $client->created_at->format('M j, Y g:i A') }}">
                                        {{ $client->created_at->format('M j, Y') }}
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button"
                                                    @click="openEdit({{ json_encode($clientPayload($client)) }})"
                                                    class="btn btn-ghost btn-icon btn-sm text-muted-foreground hover:text-foreground"
                                                    title="Edit client" aria-label="Edit {{ $client->name }}">
                                                <x-icon name="pencil" />
                                            </button>
                                            <form method="POST" action="{{ route('studio.clients.destroy', $client->uuid) }}"
                                                  @submit.prevent="$dispatch('confirm-action', { form: $el, title: @js('Delete ' . $client->name . '?'), message: 'Their contact details and notes will be removed. Past bookings stay in your records.', confirm: 'Delete client' })">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-icon btn-sm text-muted-foreground hover:text-destructive" title="Delete client" aria-label="Delete {{ $client->name }}">
                                                    <x-icon name="trash-2" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile list --}}
                <ul class="divide-y divide-border md:hidden">
                    @foreach($clients as $client)
                        <li class="flex items-center gap-3 px-4 py-3.5">
                            <x-ui.avatar :name="$client->name" size="md" />
                            <button type="button" class="min-w-0 flex-1 text-left" @click="openEdit({{ json_encode($clientPayload($client)) }})">
                                <span class="block truncate text-sm font-medium text-foreground">{{ $client->name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ $client->email ?: ($client->phone ?: ($client->company_name ?: 'No contact info')) }}</span>
                            </button>
                            @if($client->bookings_count > 0)
                                <x-ui.badge variant="neutral" class="tabular-nums">{{ $client->bookings_count }}</x-ui.badge>
                            @endif
                            <div class="relative" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
                                <button type="button" @click="toggle()" class="btn btn-ghost btn-icon btn-sm text-muted-foreground" aria-label="Actions for {{ $client->name }}" :aria-expanded="open">
                                    <x-icon name="ellipsis-vertical" />
                                </button>
                                <div x-show="open" x-transition.origin.top.right x-cloak class="menu absolute right-0 top-full mt-1 w-44">
                                    <button type="button" class="menu-item" @click="close(); openEdit({{ json_encode($clientPayload($client)) }})"><x-icon name="pencil" /> Edit details</button>
                                    @if($client->email)
                                        <a href="mailto:{{ $client->email }}" class="menu-item"><x-icon name="mail" /> Send email</a>
                                    @endif
                                    @if($client->phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" class="menu-item"><x-icon name="phone" /> Call</a>
                                    @endif
                                    <div class="menu-separator"></div>
                                    <form method="POST" action="{{ route('studio.clients.destroy', $client->uuid) }}"
                                          @submit.prevent="close(); $dispatch('confirm-action', { form: $el, title: @js('Delete ' . $client->name . '?'), message: 'Their contact details and notes will be removed. Past bookings stay in your records.', confirm: 'Delete client' })">
                                        @csrf
                                        <button type="submit" class="menu-item menu-item-danger"><x-icon name="trash-2" /> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($clients->hasPages())
            <div class="mt-6">{{ $clients->withQueryString()->links() }}</div>
        @endif
    @endif

    {{-- Create client modal --}}
    @component('studio.partials.business.modal', ['show' => 'createModalOpen', 'title' => 'Add client', 'description' => 'Save their details now. You can link bookings and galleries later.', 'icon' => 'user-plus', 'maxWidth' => 'lg'])
        <form method="POST" action="{{ route('studio.clients.store') }}">
            @csrf
            <div class="px-5 pt-4 pb-5 sm:px-6">
                @include('studio.partials.business.client-fields', ['prefix' => 'new-client', 'bind' => null])
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'createModalOpen = false', 'submit' => 'Add client'])
        </form>
    @endcomponent

    {{-- Edit client modal --}}
    @component('studio.partials.business.modal', ['show' => 'editModalOpen', 'title' => 'Edit client', 'description' => 'Update contact details and private notes.', 'icon' => 'pencil', 'maxWidth' => 'lg'])
        <form method="POST" :action="'/studio/clients/' + (selectedClient?.uuid ?? '')">
            @csrf
            @method('PATCH')
            <div class="px-5 pt-4 pb-5 sm:px-6">
                @include('studio.partials.business.client-fields', ['prefix' => 'edit-client', 'bind' => 'selectedClient'])
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'editModalOpen = false', 'submit' => 'Save changes'])
        </form>
    @endcomponent

    @include('studio.partials.business.confirm-dialog')
</div>
@endsection
