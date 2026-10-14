@extends('layouts.guest', ['title' => 'Verify your email · ifotoset', 'icon' => 'mail', 'heading' => 'Check your inbox', 'subheading' => 'We sent a verification link to ' . (auth()->user()->email ?? 'your email') . '. Click it to activate your studio.'])

@section('content')
<div class="space-y-5">
    @if (session('status') == 'verification-link-sent' || session('success'))
        <div class="alert alert-success">
            <x-icon name="circle-check" />
            <span>A fresh verification link is on its way to your inbox.</span>
        </div>
    @endif

    <div class="surface-muted space-y-2 p-4 text-[13px] text-muted-foreground">
        <p class="flex items-start gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0" /> Can’t find it? Check your spam or promotions folder. The link expires in 60 minutes.</p>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="submitting">
            <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
            <x-icon name="send" x-show="!submitting" />
            Resend verification email
        </button>
    </form>

    <div class="flex items-center justify-between border-t border-border pt-5 text-[13px]">
        <a href="{{ route('studio.dashboard') }}" class="font-medium text-muted-foreground transition hover:text-foreground">Continue to studio</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="font-medium text-muted-foreground transition hover:text-foreground">Sign out</button>
        </form>
    </div>
</div>
@endsection
