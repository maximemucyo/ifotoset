@extends('layouts.guest', ['title' => 'Set a new password · ifotoset', 'icon' => 'lock', 'heading' => 'Set a new password', 'subheading' => 'Choose a strong password you don’t use anywhere else.'])

@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <x-ui.input label="Email"
                type="email"
                name="email"
                id="email"
                :value="old('email', $email)"
                required
                autocomplete="email"
                :error="$errors->first('email')" />

    <x-ui.input label="New password"
                type="password"
                name="password"
                id="password"
                required
                autofocus
                autocomplete="new-password"
                placeholder="At least 8 characters"
                :error="$errors->first('password')" />

    <x-ui.input label="Confirm new password"
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repeat your new password" />

    <button type="submit" class="btn btn-primary btn-lg w-full !mt-6" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        Update password
    </button>
</form>
@endsection
