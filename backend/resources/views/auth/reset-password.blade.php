@extends('layouts.guest', ['title' => 'Set New Password - ifotoset', 'heading' => 'Set a new password', 'subheading' => 'Please choose a strong password for your account.'])

@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <x-ui.input label="Email address"
                type="email"
                name="email"
                id="email"
                :value="old('email', $email)"
                required
                autocomplete="email"
                :error="$errors->first('email')" />

    <x-ui.input label="New Password"
                type="password"
                name="password"
                id="password"
                required
                autofocus
                autocomplete="new-password"
                placeholder="At least 8 characters"
                :error="$errors->first('password')" />

    <x-ui.input label="Confirm New Password"
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm new password" />

    <x-ui.button type="submit" variant="primary" class="w-full mt-2">
        Update Password
    </x-ui.button>
</form>
@endsection
