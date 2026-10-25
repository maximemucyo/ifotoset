@extends('layouts.guest', ['title' => 'Sign in · ifotoset', 'heading' => 'Welcome back', 'subheading' => 'Sign in to your studio to manage galleries, bookings and clients.'])

@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
    @csrf

    @include('auth.partials.google-button', ['label' => 'Continue with Google'])

    <x-ui.input label="Email"
                type="email"
                name="email"
                id="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="email"
                placeholder="you@studio.com"
                :error="$errors->first('email')" />

    <div>
        <div class="mb-1.5 flex items-center justify-between">
            <label for="password" class="text-[13px] font-medium text-foreground">Password</label>
            <a href="{{ route('password.request') }}" class="text-[13px] font-medium text-muted-foreground transition hover:text-foreground">Forgot password?</a>
        </div>
        <x-ui.input type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    :error="$errors->first('password')" />
    </div>

    <label class="flex cursor-pointer items-center gap-2.5 py-1">
        <input type="checkbox" name="remember" class="checkbox" {{ old('remember') ? 'checked' : '' }}>
        <span class="text-[13px] text-muted-foreground">Keep me signed in for 30 days</span>
    </label>

    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        <span>Sign in</span>
        <x-icon name="arrow-right" x-show="!submitting" />
    </button>

    <p class="pt-4 text-center text-[13px] text-muted-foreground">
        New to ifotoset?
        <a href="{{ route('register') }}" class="link">Create a free account</a>
    </p>
</form>
@endsection
