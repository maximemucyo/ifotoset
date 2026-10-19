@extends('layouts.guest', ['title' => 'Reset password · ifotoset', 'icon' => 'key', 'heading' => 'Forgot your password?', 'subheading' => 'Enter the email you signed up with and we’ll send you a secure reset link.'])

@section('content')
<form method="POST" action="{{ route('password.email') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
    @csrf

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

    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        Send reset link
    </button>

    <a href="{{ route('login') }}" class="flex items-center justify-center gap-1.5 pt-3 text-[13px] font-medium text-muted-foreground transition hover:text-foreground">
        <x-icon name="arrow-left" class="size-3.5" /> Back to sign in
    </a>
</form>
@endsection
