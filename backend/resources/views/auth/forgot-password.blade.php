@extends('layouts.guest', ['title' => 'Forgot Password - ifotoset', 'heading' => 'Reset your password', 'subheading' => 'Enter your registered email and we will send you a reset link.'])

@section('content')
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf

    <x-ui.input label="Email address"
                type="email"
                name="email"
                id="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="email"
                placeholder="you@example.com"
                :error="$errors->first('email')" />

    <x-ui.button type="submit" variant="primary" class="w-full">
        Send Reset Link
    </x-ui.button>

    <div class="text-center pt-2">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-primary hover:underline">
            &larr; Back to sign in
        </a>
    </div>
</form>
@endsection
