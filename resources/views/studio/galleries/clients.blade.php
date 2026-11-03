@extends('layouts.app', ['title' => 'Clients · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@php
    $inviteExplainer = match ($gallery->visibility) {
        'private' => 'Only people you invite can open this gallery. Each person gets a personal link by email.',
        'password' => 'Each person gets a personal link by email that opens the gallery without the password.',
        default => 'Anyone with the link can open this gallery. Inviting people by email sends them the link and shows you when they open it.',
    };
    $openedCount = $invitations->whereNotNull('accepted_at')->count();
    $isExpired = $gallery->expires_at?->isPast();
@endphp

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="clients">

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        {{-- Invite form: first on phones, beside the list on wide screens --}}
        <form method="POST" action="{{ route('studio.galleries.invitations.store', $gallery->uuid) }}" enctype="multipart/form-data"
              class="card space-y-4 p-5 sm:p-6 lg:sticky lg:top-6 lg:order-2"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div>
                <h2 class="text-[15px] font-semibold text-foreground">Invite people</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">{{ $inviteExplainer }}</p>
            </div>

            @if($isExpired)
                <p class="rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-foreground">
                    This gallery expired, so invitations won't open it. Change the expiry date in
                    <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}#expiry" class="font-medium underline underline-offset-2">Settings</a> to reopen it.
                </p>
            @endif

            @include('studio.galleries.partials.invite-fields')

            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('studio.galleries.invitations.template') }}" class="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline">CSV template</a>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting">
                    <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                    Send invitations
                </button>
            </div>
        </form>

        {{-- Who has been invited --}}
        <section class="lg:order-1" aria-labelledby="invited-heading">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 id="invited-heading" class="text-[15px] font-semibold text-foreground">Invited</h2>
                @if($invitations->isNotEmpty())
                    <p class="text-[13px] tabular-nums text-muted-foreground">{{ $openedCount }} of {{ $invitations->count() }} opened</p>
                @endif
            </div>

            @if($invitations->isEmpty())
                <div class="rounded-xl border border-dashed border-border-strong px-6 py-10 text-center">
                    <p class="text-sm font-medium text-foreground">No one invited yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">Add your client's email to send them the gallery. You'll see here when they open it.</p>
                </div>
            @else
                <ul class="card divide-y divide-border">
                    @foreach($invitations as $invitation)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ $invitation->email }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    @if($invitation->isRevoked())
                                        <span class="font-medium text-destructive">Access revoked</span>
                                    @elseif($invitation->isExpired())
                                        <span class="font-medium text-warning">Expired with the gallery</span>
                                    @elseif($invitation->accepted_at)
                                        <span class="font-medium text-success">Opened {{ $invitation->accepted_at->diffForHumans() }}</span>
                                    @else
                                        Not opened yet. Invited {{ $invitation->created_at->diffForHumans() }}.
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1 sm:justify-end">
                                <form method="POST" action="{{ route('studio.galleries.invitations.resend', [$gallery->uuid, $invitation->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-xs">{{ $invitation->isRevoked() ? 'Invite again' : 'Resend' }}</button>
                                </form>
                                {{-- Revoking only locks someone out when the link alone doesn't open the gallery --}}
                                @unless($invitation->isRevoked() || $gallery->visibility === 'public')
                                    <form method="POST" action="{{ route('studio.galleries.invitations.revoke', [$gallery->uuid, $invitation->id]) }}"
                                          onsubmit="return confirm(@js('Revoke access for ' . $invitation->email . '?'))">
                                        @csrf
                                        <button type="submit" class="btn btn-danger-ghost btn-xs">Revoke</button>
                                    </form>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    </x-studio.gallery-workspace>
</div>
@endsection
