@extends('layouts.guest', ['title' => 'Sign In - ifotoset', 'heading' => 'Sign in to your account', 'subheading' => 'Access your studio, galleries, and bookings.'])

@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-5">
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

    <div>
        <div class="flex items-center justify-between mb-1.5">
            <label for="password" class="block text-sm font-medium text-foreground">Password</label>
            <a href="{{ route('password.request') }}" class="text-xs font-medium text-primary hover:underline">
                Forgot password?
            </a>
        </div>
        <x-ui.input type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    :error="$errors->first('password')" />
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
            <span class="text-xs font-medium text-muted-foreground">Remember me for 30 days</span>
        </label>
    </div>

    <x-ui.button type="submit" variant="primary" class="w-full">
        Sign in to Studio
    </x-ui.button>

    <div class="text-center pt-2">
        <p class="text-xs text-muted-foreground">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">
                Create one for free
            </a>
        </p>
    </div>
</form>
@endsection
