@extends('layouts.app', ['title' => 'Team · ifotoset'])

@php
    $canManage = Gate::allows('studio.team.manage');
    $seatPct = $seatLimit > 0 ? min(100, round($seatsUsed / $seatLimit * 100)) : 0;
    $seatsFull = $seatsUsed >= $seatLimit;
@endphp

@section('breadcrumbs')
    <span>Account</span>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Team</span>
@endsection

@section('content')
<x-ui.page-header title="Team" description="Invite the people who work with you. Everyone signs in with their own login and works in this studio's galleries, bookings and clients." />

@if(!$hasTeams)
    {{-- Plan without team seats --}}
    <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand"><x-icon name="user-plus" class="size-5" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-foreground">Team accounts come with the Business plan</p>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Give your second shooters, editors and assistants their own logins. Business includes 5 team members plus you.</p>
        </div>
        @can('studio.billing')
            <a href="{{ route('studio.billing.index') }}" class="btn btn-brand shrink-0"><x-icon name="zap" /> View plans</a>
        @endcan
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-6">
            {{-- Members --}}
            <section class="card overflow-hidden" aria-labelledby="members-heading">
                <div class="card-header">
                    <div>
                        <h2 id="members-heading" class="card-title">Members</h2>
                        <p class="card-description">The owner plus {{ $members->count() }} {{ \Illuminate\Support\Str::plural('member', $members->count()) }}.</p>
                    </div>
                </div>
                <ul class="divide-y divide-border border-t border-border">
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <x-ui.avatar :name="$account->name" :src="$account->avatar_url" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ $account->name }}</p>
                                <p class="truncate text-[13px] text-muted-foreground">{{ $account->email }}</p>
                            </div>
                        </div>
                        <x-ui.badge variant="brand">Owner</x-ui.badge>
                    </li>
                    @foreach($members as $member)
                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <x-ui.avatar :name="$member->user->name" :src="$member->user->avatar_url" size="sm" />
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 truncate text-sm font-medium text-foreground">
                                        {{ $member->user->name }}
                                        @if($member->isSuspended())
                                            <x-ui.badge variant="warning">Paused</x-ui.badge>
                                        @endif
                                    </p>
                                    <p class="truncate text-[13px] text-muted-foreground">{{ $member->user->email }} · Joined {{ $member->created_at->format('M j, Y') }}</p>
                                </div>
                            </div>
                            @if($canManage)
                                <div class="flex shrink-0 items-center gap-2">
                                    <form method="POST" action="{{ route('studio.team.role', $member->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <label for="role-{{ $member->id }}" class="sr-only">Role for {{ $member->user->name }}</label>
                                        <select id="role-{{ $member->id }}" name="role" class="select input-sm w-32" onchange="this.form.submit()">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form method="POST" action="{{ route('studio.team.remove', $member->id) }}"
                                          onsubmit="return confirm(@js('Remove ' . $member->user->name . ' from the team? Their login stays, but they lose access to this studio.'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remove {{ $member->user->name }}"><x-icon name="user-x" /></button>
                                    </form>
                                </div>
                            @else
                                <x-ui.badge variant="neutral">{{ $member->role->label() }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($members->contains(fn ($m) => $m->isSuspended()))
                    <p class="border-t border-border px-5 py-3 text-[13px] text-muted-foreground">
                        <x-icon name="info" class="inline size-3.5" /> Paused members don't fit in your current plan's seats. They get access back when seats free up.
                    </p>
                @endif
            </section>

            {{-- Pending invitations --}}
            @if($invitations->isNotEmpty())
                <section class="card overflow-hidden" aria-labelledby="invites-heading">
                    <div class="card-header">
                        <div>
                            <h2 id="invites-heading" class="card-title">Pending invitations</h2>
                            <p class="card-description">Each pending invitation holds a seat until it's accepted, cancelled or expires.</p>
                        </div>
                    </div>
                    <ul class="divide-y divide-border border-t border-border">
                        @foreach($invitations as $invitation)
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"><x-icon name="mail" class="size-4" /></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-foreground">{{ $invitation->email }}</p>
                                        <p class="truncate text-[13px] text-muted-foreground">{{ $invitation->role->label() }} · Expires {{ $invitation->expires_at->format('M j') }}</p>
                                    </div>
                                </div>
                                @if($canManage)
                                    <div class="flex shrink-0 items-center gap-2">
                                        <form method="POST" action="{{ route('studio.team.resend', $invitation->uuid) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm"><x-icon name="send" /> Resend</button>
                                        </form>
                                        <form method="POST" action="{{ route('studio.team.revoke', $invitation->uuid) }}"
                                              onsubmit="return confirm(@js('Cancel the invitation for ' . $invitation->email . '?'))">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">Cancel</button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            {{-- Seat meter --}}
            <div class="card p-5">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-foreground">Seats</span>
                    <span class="tabular-nums text-muted-foreground">{{ $seatsUsed }} of {{ $seatLimit }} used</span>
                </div>
                <div class="progress mt-3"><span style="width: {{ max($seatPct, 1.5) }}%" class="{{ $seatsFull ? '!bg-warning' : '' }}"></span></div>
                <p class="mt-2 text-xs text-muted-foreground">You don't take a seat. Pending invitations do.</p>
            </div>

            {{-- Invite --}}
            @if($canManage)
                <form method="POST" action="{{ route('studio.team.invite') }}" class="card">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Invite a member</h2>
                            <p class="card-description">They'll get an email with a link to join. It expires in 7 days.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-4">
                        <x-ui.input label="Email" name="email" type="email" required autocomplete="off" placeholder="name@example.com"
                                    :value="old('email')" :error="$errors->first('email')" :disabled="$seatsFull" />
                        <fieldset>
                            <legend class="label">Role</legend>
                            <div class="space-y-2">
                                @foreach($roles as $role)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 has-[:checked]:border-ring">
                                        <input type="radio" name="role" value="{{ $role->value }}" class="mt-1" @checked(old('role', 'member') === $role->value) @disabled($seatsFull)>
                                        <span>
                                            <span class="block text-sm font-medium text-foreground">{{ $role->label() }}</span>
                                            <span class="block text-[13px] text-muted-foreground">{{ $role->description() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('role')<p class="error-text">{{ $message }}</p>@enderror
                        </fieldset>
                    </div>
                    <div class="card-footer flex items-center justify-end gap-3">
                        @if($seatsFull)
                            <p class="mr-auto text-xs text-muted-foreground">All seats are taken.</p>
                        @endif
                        <button type="submit" class="btn btn-primary" @disabled($seatsFull)><x-icon name="send" /> Send invitation</button>
                    </div>
                </form>
            @else
                <p class="text-[13px] text-muted-foreground">Only the studio owner can invite, remove or change team members.</p>
            @endif
        </aside>
    </div>
@endif
@endsection
