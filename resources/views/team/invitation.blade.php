@php
    $studioName = $invitation?->account->name;
    [$heading, $subheading] = match ($state) {
        'invalid' => ['Invitation unavailable', $message],
        'register' => ["Join {$studioName}", "Create your login to start working in {$studioName} on ifotoset."],
        default => ["Join {$studioName}", ($invitation->inviter?->name ?? $studioName) . " invited you to their studio on ifotoset."],
    };
@endphp
@extends('layouts.guest', ['title' => 'Team invitation · ifotoset', 'heading' => $heading, 'subheading' => $subheading])

@section('content')
@if($state === 'invalid')
    <a href="{{ auth()->check() ? route('studio.dashboard') : route('login') }}" class="btn btn-primary btn-lg w-full">
        {{ auth()->check() ? 'Go to my studio' : 'Log in' }} <x-icon name="arrow-right" />
    </a>
@else
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-border bg-muted/50 p-4">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="building" class="size-5" /></span>
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-foreground">{{ $studioName }}</p>
            <p class="text-[13px] text-muted-foreground">{{ $invitation->role->label() }} · {{ $invitation->role->description() }}</p>
        </div>
    </div>

    @if($state === 'confirm')
        <form method="POST" action="{{ route('team.invitation.accept', $token) }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="submitting">
                <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
                Join {{ $studioName }} <x-icon name="arrow-right" x-show="!submitting" />
            </button>
        </form>
        <p class="mt-4 text-center text-[13px] text-muted-foreground">You can switch between studios from the menu at the bottom of the sidebar.</p>
    @elseif($state === 'wrong_user')
        <div class="alert alert-warning">
            <x-icon name="triangle-alert" />
            <div>
                <p class="font-medium">This invitation is for {{ $invitation->email }}.</p>
                <p class="mt-0.5 text-[13px]">You're signed in as {{ auth()->user()->email }}. Sign out, then open the link again to accept.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-outline btn-lg w-full"><x-icon name="log-out" /> Sign out</button>
        </form>
    @else
        <form method="POST" action="{{ route('team.invitation.register', $token) }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @include('auth.partials.google-button', ['label' => 'Continue with Google', 'href' => route('team.invitation.google', $token)])

            <div>
                <span class="label">Email</span>
                <p class="flex h-10 items-center rounded-lg border border-border bg-muted px-3 text-sm text-muted-foreground">{{ $invitation->email }}</p>
            </div>
            <x-ui.input label="Full name" type="text" name="name" id="name" :value="old('name')" required autofocus autocomplete="name"
                        placeholder="Alex Morgan" :error="$errors->first('name')" />
            <x-ui.input label="Password" type="password" name="password" id="password" required autocomplete="new-password"
                        placeholder="At least 8 characters" :error="$errors->first('password')" />
            <x-ui.input label="Confirm password" type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" />

            <button type="submit" class="btn btn-primary btn-lg w-full !mt-6" :disabled="submitting">
                <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
                <span>Join {{ $studioName }}</span>
                <x-icon name="arrow-right" x-show="!submitting" />
            </button>

            <p class="text-center text-xs leading-relaxed text-muted-foreground">
                By joining you agree to our <a href="{{ route('legal.terms') }}" class="link">Terms of Service</a> and <a href="{{ route('legal.privacy') }}" class="link">Privacy Policy</a>.
            </p>
        </form>
    @endif
@endif
@endsection
