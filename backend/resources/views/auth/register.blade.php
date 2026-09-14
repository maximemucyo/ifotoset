@extends('layouts.guest', ['title' => 'Create an Account - ifotoset', 'heading' => 'Start your 14-day free trial', 'subheading' => 'No credit card required. Deliver client galleries effortlessly.'])

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf

    <x-ui.input label="Full Name"
                type="text"
                name="name"
                id="name"
                :value="old('name')"
                required
                autofocus
                autocomplete="name"
                placeholder="Alex Morgan"
                :error="$errors->first('name')" />

    <x-ui.input label="Photographer Username"
                type="text"
                name="username"
                id="username"
                :value="old('username')"
                required
                autocomplete="username"
                placeholder="alexmorgan"
                :error="$errors->first('username')" />
    <p class="text-[11px] text-muted-foreground -mt-2">Your public portfolio will be at ifotoset.com/p/<span class="font-mono text-primary font-semibold" x-data x-text="$el.closest('form').querySelector('#username').value || 'username'">username</span></p>

    <x-ui.input label="Email address"
                type="email"
                name="email"
                id="email"
                :value="old('email')"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :error="$errors->first('email')" />

    <x-ui.input label="Password"
                type="password"
                name="password"
                id="password"
                required
                autocomplete="new-password"
                placeholder="At least 8 characters"
                :error="$errors->first('password')" />

    <x-ui.input label="Confirm Password"
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm your password" />

    <x-ui.button type="submit" variant="primary" class="w-full mt-2">
        Create Studio Account
    </x-ui.button>

    <div class="text-center pt-2">
        <p class="text-xs text-muted-foreground">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">
                Sign in
            </a>
        </p>
    </div>
</form>
@endsection
