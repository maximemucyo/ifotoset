@extends('layouts.guest', ['title' => 'Verify Your Email - ifotoset', 'heading' => 'Verify your email address', 'subheading' => 'Before accessing your studio, please check your email for a verification link.'])

@section('content')
<div class="space-y-5 text-sm text-foreground">
    <p class="text-muted-foreground leading-relaxed">
        Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
    </p>

    @if (session('status') == 'verification-link-sent' || session('success'))
        <div class="p-3 bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-300 text-xs rounded-lg font-medium">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <div class="pt-2 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" variant="primary">
                Resend Verification Email
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-muted-foreground hover:text-foreground underline">
                Log Out
            </button>
        </form>
    </div>
</div>
@endsection
